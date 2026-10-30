"""Endpoint binding tests with synthetic HTTP responses; no network."""
import copy
import importlib
import json
import unittest

from profile_receipts import EvidenceError, HttpReceipt
from profile_transport import HttpExchange

ORIGIN = 'https://localhost:18443'
SCOPES = ['activity.read', 'campaigns.read', 'campaigns.write', 'contacts.read',
          'contacts.write', 'mail.send', 'suppressions.read', 'suppressions.write',
          'templates.read', 'templates.write']


class EndpointTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_endpoints')
        self.calls = []
        self.records = []
        self.replies = []

    def response(self, operation, data, *, status=200, headers=(), error=None, raw=None):
        body = json.dumps(data).encode() if raw is None else raw
        return HttpExchange(HttpReceipt(operation, 'GET', status, 1, len(body), error),
                            (('Content-Type', 'application/json'),
                             ('Cache-Control', 'no-store'), *headers), body)

    def server(self):
        return {'issuer': ORIGIN, 'authorization_endpoint': ORIGIN + '/oauth/authorize',
                'token_endpoint': ORIGIN + '/oauth/token', 'revocation_endpoint': ORIGIN + '/oauth/revoke',
                'response_types_supported': ['code'], 'grant_types_supported': ['authorization_code'],
                'code_challenge_methods_supported': ['S256'], 'token_endpoint_auth_methods_supported': ['none'],
                'revocation_endpoint_auth_methods_supported': ['none'],
                'authorization_response_iss_parameter_supported': True,
                'client_id_metadata_document_supported': False, 'scopes_supported': SCOPES}

    def resource(self):
        return {'resource': ORIGIN + '/mcp', 'authorization_servers': [ORIGIN],
                'scopes_supported': [], 'bearer_methods_supported': ['header'], 'resource_name': 'Emailing'}

    def request(self, *args, **kwargs):
        self.calls.append((args, kwargs))
        return self.replies.pop(0)

    def checks(self):
        return self.core.EndpointChecks(ORIGIN, request=self.request, record=self.records.append)

    def ready_replies(self, server=None, resource=None):
        self.replies.extend([
            self.response('readiness', {}, raw=b'<!doctype html>Healthy'),
            self.response('authorization_metadata', server or self.server()),
            self.response('resource_metadata', resource or self.resource()),
            self.response('resource_metadata', resource or self.resource())])

    def test_checks_readiness_and_both_resource_metadata_endpoints_without_credentials(self):
        self.ready_replies()
        proof = self.checks().run()
        self.assertEqual(len(self.calls), 4)
        self.assertEqual(len(self.records), 4)
        for (operation, method, url), kwargs in self.calls:
            self.assertEqual(method, 'GET')
            self.assertTrue(url.startswith(ORIGIN + '/'))
            self.assertNotIn('Authorization', kwargs.get('headers', {}))
        self.assertIs(proof['profile_qualified'], False)
        self.assertEqual(proof['metadata_verified'], 3)

    def test_mismatched_token_or_registration_authority_cannot_pass_binding(self):
        for key in ['issuer', 'token_endpoint', 'authorization_endpoint', 'revocation_endpoint', 'registration_endpoint']:
            server = self.server()
            server[key] = 'https://fixture-private.invalid/oauth/token'
            self.ready_replies(server=server)
            with self.assertRaises(EvidenceError) as caught:
                self.checks().run()
            self.assertNotIn('fixture-private', str(caught.exception))
            self.replies.clear()

    def test_registration_when_present_is_only_the_exact_local_endpoint(self):
        server = self.server()
        server['registration_endpoint'] = ORIGIN + '/oauth/register'
        self.ready_replies(server=server)
        self.assertTrue(self.checks().run()['dynamic_registration_advertised'])

    def test_scope_duplicates_unknown_scope_or_missing_scope_are_rejected(self):
        for scopes in [SCOPES[:-1], SCOPES + [SCOPES[0]], SCOPES + ['fixture.private']]:
            server = self.server()
            server['scopes_supported'] = scopes
            self.ready_replies(server=server)
            with self.assertRaises(EvidenceError):
                self.checks().run()
            self.replies.clear()

    def test_pkce_public_client_and_no_refresh_contract_is_enforced(self):
        for key, value in [('code_challenge_methods_supported', ['plain']),
                           ('grant_types_supported', ['authorization_code', 'refresh_token']),
                           ('token_endpoint_auth_methods_supported', ['client_secret_basic']),
                           ('authorization_response_iss_parameter_supported', 1)]:
            server = self.server()
            server[key] = value
            self.ready_replies(server=server)
            with self.assertRaises(EvidenceError):
                self.checks().run()
            self.replies.clear()

    def test_resource_alias_must_match_and_not_advertise_a_different_authorization_server(self):
        for resource in [{**self.resource(), 'resource': ORIGIN + '/other'},
                         {**self.resource(), 'authorization_servers': ['https://fixture-private.invalid']},
                         {**self.resource(), 'bearer_methods_supported': ['query']},
                         {**self.resource(), 'scopes_supported': ['contacts.read']}]:
            self.ready_replies(resource=resource)
            with self.assertRaises(EvidenceError):
                self.checks().run()
            self.replies.clear()

    def test_redirect_or_transport_error_records_observation_and_stops_without_retry(self):
        self.replies.append(self.response('readiness', {}, status=302, error='redirect_rejected'))
        with self.assertRaises(EvidenceError):
            self.checks().run()
        self.assertEqual(len(self.records), 1)
        self.assertEqual(len(self.calls), 1)

    def test_metadata_requires_json_no_store_and_no_duplicate_fields(self):
        variants = [self.response('authorization_metadata', {}, raw=b'{"issuer":1,"issuer":2}'),
                    self.response('authorization_metadata', self.server(),
                                  headers=(('Content-Type', 'application/json'),)),
                    HttpExchange(HttpReceipt('authorization_metadata', 'GET', 200, 1, 2),
                                 (('Content-Type', 'application/json'),), b'{}')]
        for response in variants:
            self.replies.extend([self.response('readiness', {}), response])
            with self.assertRaises(EvidenceError):
                self.checks().run()
            self.replies.clear()

    def test_arbitrary_server_diagnostics_are_not_exposed(self):
        def broken(*args, **kwargs):
            raise OSError('fixture-private-path-and-credential')
        checks = self.core.EndpointChecks(ORIGIN, request=broken, record=self.records.append)
        with self.assertRaises(EvidenceError) as caught:
            checks.run()
        self.assertNotIn('fixture-private', str(caught.exception))


if __name__ == '__main__':
    unittest.main()
