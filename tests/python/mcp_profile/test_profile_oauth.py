"""Bound public-client token exchange/revocation; synthetic HTTP only."""
import importlib
import json
import unittest
from urllib.parse import parse_qs
from uuid import uuid4

from profile_receipts import EvidenceError, HttpReceipt
from profile_transport import HttpExchange

ORIGIN = 'https://localhost:18443'


class OAuthProfileTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_oauth')
        self.client_id = str(uuid4())
        self.calls = []
        self.records = []
        self.replies = []
        self.token = '71|' + 'A' * 64

    def request(self, *args, **kwargs):
        self.calls.append((args, kwargs))
        return self.replies.pop(0)

    def flow(self):
        return self.core.OAuthExchange(ORIGIN, client_id=self.client_id,
                                       scopes=['contacts.read'], request=self.request,
                                       record=self.records.append)

    def reply(self, document=None, *, operation='token_exchange', status=200,
              error=None, raw=None, headers=None):
        document = document or {'access_token': self.token, 'token_type': 'Bearer',
                                'expires_in': 3599, 'scope': 'contacts.read'}
        body = json.dumps(document).encode() if raw is None else raw
        return HttpExchange(HttpReceipt(operation, 'POST', status, 1, len(body), error),
                            headers if headers is not None else (
                                ('Content-Type', 'application/json'), ('Cache-Control', 'no-store')), body)

    def exchange(self, flow=None, **changes):
        values = {'code': 'C' * 64, 'verifier': 'v' * 43,
                  'redirect_uri': 'http://127.0.0.1:18821/callback'}
        values.update(changes)
        return (flow or self.flow()).exchange(**values)

    def test_exchange_is_bound_to_issuer_resource_public_client_and_pkce_without_secret_or_bearer(self):
        self.replies.append(self.reply())
        credential = self.exchange()
        (operation, method, url), kwargs = self.calls[0]
        self.assertEqual((operation, method, url), ('token_exchange', 'POST', ORIGIN + '/oauth/token'))
        self.assertEqual(parse_qs(kwargs['body'].decode()), {
            'grant_type': ['authorization_code'], 'client_id': [self.client_id],
            'code': ['C' * 64], 'code_verifier': ['v' * 43],
            'redirect_uri': ['http://127.0.0.1:18821/callback'], 'resource': [ORIGIN + '/mcp']})
        self.assertNotIn('Authorization', kwargs['headers'])
        self.assertEqual(credential.bearer, self.token)
        self.assertEqual(len(self.records), 1)

    def test_credential_representation_and_summary_never_expose_token_or_code(self):
        self.replies.append(self.reply())
        credential = self.exchange()
        self.assertNotIn(self.token, repr(credential))
        self.assertNotIn(self.token, json.dumps(credential.summary()))
        self.assertIs(credential.summary()['profile_qualified'], False)

    def test_consumed_or_uncertain_exchange_is_never_retried(self):
        flow = self.flow()
        self.replies.append(self.reply(status=400, error='http_error'))
        with self.assertRaises(EvidenceError):
            self.exchange(flow)
        with self.assertRaises(EvidenceError):
            self.exchange(flow)
        self.assertEqual(len(self.calls), 1)
        self.assertEqual(len(self.records), 1)

    def test_transport_failure_is_bounded_and_does_not_echo_diagnostics(self):
        def broken(*args, **kwargs):
            self.calls.append((args, kwargs))
            raise OSError('fixture-private-endpoint')
        flow = self.core.OAuthExchange(ORIGIN, client_id=self.client_id, scopes=['contacts.read'],
                                       request=broken, record=self.records.append)
        with self.assertRaises(EvidenceError) as caught:
            self.exchange(flow)
        self.assertNotIn('fixture-private', str(caught.exception))
        with self.assertRaises(EvidenceError):
            self.exchange(flow)
        self.assertEqual(len(self.calls), 1)

    def test_invalid_pkce_code_or_callback_is_rejected_before_credentials_are_sent(self):
        for changes in [{'verifier': 'v' * 42}, {'verifier': 'v' * 129}, {'verifier': '\ud800'},
                        {'code': 'short'}, {'code': 'C' * 64 + '\r\n'},
                        {'redirect_uri': 'https://fixture-private.invalid/callback'},
                        {'redirect_uri': 'http://user@127.0.0.1:18821/callback'},
                        {'redirect_uri': 'http://127.0.0.1:18821/callback?fixture-private=1'}]:
            with self.assertRaises(EvidenceError) as caught:
                self.exchange(**changes)
            self.assertNotIn('fixture-private', str(caught.exception))
        self.assertEqual(self.calls, [])

    def test_token_response_enforces_exact_types_scope_and_no_refresh_contract(self):
        base = {'access_token': self.token, 'token_type': 'Bearer', 'expires_in': 3599, 'scope': 'contacts.read'}
        for changes in [{'expires_in': True}, {'expires_in': 0}, {'expires_in': 3601},
                        {'token_type': 'Basic'}, {'scope': 'contacts.read templates.read'},
                        {'scope': 'contacts.read contacts.read'}, {'refresh_token': 'fixture-private'},
                        {'access_token': 'fixture-private-invalid'}]:
            self.replies.append(self.reply({**base, **changes}))
            with self.assertRaises(EvidenceError):
                self.exchange()

    def test_success_requires_json_no_store_and_unique_decoded_members(self):
        for response in [self.reply(headers=(('Content-Type', 'application/json'),)),
                         self.reply(raw=b'{"access_token":"a","access_token":"b"}'),
                         self.reply(headers=(('Content-Type', 'text/html'), ('Cache-Control', 'no-store')))]:
            self.replies.append(response)
            with self.assertRaises(EvidenceError):
                self.exchange()

    def test_revocation_posts_only_the_bound_token_and_public_client_then_returns_partial_acknowledgement(self):
        flow = self.flow()
        self.replies.append(self.reply())
        credential = self.exchange(flow)
        self.replies.append(self.reply(operation='token_revocation', raw=b''))
        proof = flow.revoke(credential)
        (operation, method, url), kwargs = self.calls[-1]
        self.assertEqual((operation, method, url), ('token_revocation', 'POST', ORIGIN + '/oauth/revoke'))
        self.assertEqual(parse_qs(kwargs['body'].decode()), {'client_id': [self.client_id], 'token': [self.token]})
        self.assertIs(proof['profile_qualified'], False)
        self.assertIs(proof['subsequent_http401_verified'], False)

    def test_revocation_cannot_forward_a_credential_from_another_client(self):
        self.replies.append(self.reply())
        credential = self.exchange()
        other = self.core.OAuthExchange(ORIGIN, client_id=str(uuid4()), scopes=['contacts.read'],
                                        request=self.request, record=self.records.append)
        with self.assertRaises(EvidenceError):
            other.revoke(credential)
        self.assertEqual(len(self.calls), 1)

    def test_revocation_http200_with_unexpected_body_is_not_accepted(self):
        flow = self.flow()
        self.replies.append(self.reply())
        credential = self.exchange(flow)
        self.replies.append(self.reply(operation='token_revocation', raw=b'{"error":"fixture-private"}'))
        with self.assertRaises(EvidenceError):
            flow.revoke(credential)
        self.assertEqual(len(self.records), 2)


if __name__ == '__main__':
    unittest.main()
