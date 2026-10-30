"""Smoke composition and authority checks, using owned synthetic fixtures."""
import importlib
import json
from pathlib import Path
import unittest
from unittest.mock import Mock
from uuid import uuid4

from profile_receipts import EvidenceError

ROOT = Path(__file__).resolve().parents[3]
ORIGIN = 'https://localhost:18443'


class ProfileSmokeTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_smoke')
        self.grant = str(uuid4())
        self.raw = {'schema_version': 1, 'token': 'fixture-private-token', 'account_id': 71,
                    'user_id': 72, 'grant_public_id': self.grant, 'contact_id': 73,
                    'denied_template_id': 74}
        self.calls = []
        self.info = {'data': {'account': {'id': 71}, 'principal': {'user_id': 72},
                     'server': {'issuer_uri': ORIGIN, 'resource_uri': ORIGIN + '/mcp'},
                     'connection': {'public_id': self.grant, 'effective_scopes': ['contacts.read']}}}
        self.contact = {'data': {'contact': {'id': 73}}}
        self.endpoints = Mock()
        self.endpoints.run.return_value = {'metadata_verified': 3, 'profile_qualified': False}
        self.session = Mock()
        def allowed(name, args, *, validate_fixture):
            self.calls.append((name, args))
            value = self.info if name == 'connection_info' else self.contact
            if validate_fixture(value) is not True:
                raise EvidenceError('fixture_mismatch')
        self.session.allowed.side_effect = allowed
        self.session.registry.return_value = {'tools_verified': 6, 'profile_qualified': False}

    def inputs(self, value=None):
        return self.core.SmokeInputs.from_bytes(json.dumps(value or self.raw).encode())

    def run_smoke(self):
        smoke = self.core.ProfileSmoke(ORIGIN, endpoints=self.endpoints, session=self.session)
        return smoke.run(self.inputs())

    def test_only_read_only_owned_fixture_operations_are_issued(self):
        proof = self.run_smoke()
        self.assertEqual(self.calls, [('connection_info', {}), ('contacts_get', {'id': 73})])
        self.session.denied.assert_called_once_with('templates_get', {'id': 74})
        self.session.registry.assert_called_once_with(['contacts.read'])
        self.assertIs(proof['profile_qualified'], False)
        self.assertIs(proof['native_client_qualified'], False)

    def test_inputs_do_not_offer_a_public_credential_or_arbitrary_diagnostic_channel(self):
        inputs = self.inputs()
        self.assertNotIn('fixture-private', repr(inputs))
        self.assertNotIn('fixture-private', json.dumps(inputs.summary()))
        extra = {**self.raw, 'arbitrary': 'fixture-private-data'}
        with self.assertRaises(EvidenceError) as caught:
            self.inputs(extra)
        self.assertNotIn('fixture-private', str(caught.exception))

    def test_ids_are_positive_exact_integers_and_not_bools(self):
        for key in ['account_id', 'user_id', 'contact_id', 'denied_template_id']:
            for value in [True, 0, -1, 1.0, 9007199254740992, '73']:
                with self.assertRaises(EvidenceError):
                    self.inputs({**self.raw, key: value})

    def test_malformed_json_duplicate_fields_or_unbounded_input_fail_closed(self):
        for raw in [b'fixture-private', b'{"schema_version":1,"schema_version":1}',
                    b'x' * 16385, b'[]', b'null']:
            with self.assertRaises(EvidenceError):
                self.core.SmokeInputs.from_bytes(raw)

    def test_tokens_and_grant_ids_are_checked_without_echoing_them(self):
        for key, value in [('token', ''), ('token', 'fixture-private\r\n'),
                           ('token', True), ('token', '\ud800'),
                           ('grant_public_id', 'fixture-private'), ('schema_version', True)]:
            with self.assertRaises(EvidenceError) as caught:
                self.inputs({**self.raw, key: value})
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_metadata_failure_prevents_authenticated_handshake(self):
        self.endpoints.run.side_effect = EvidenceError('endpoint_failed')
        with self.assertRaises(EvidenceError):
            self.run_smoke()
        self.session.open.assert_not_called()

    def test_wrong_account_user_grant_or_issuer_prevents_access_to_fixture_data(self):
        variants = [('account', 'id', 99), ('principal', 'user_id', 99),
                    ('connection', 'public_id', str(uuid4())),
                    ('server', 'issuer_uri', 'https://fixture-private.invalid'),
                    ('server', 'resource_uri', ORIGIN + '/different')]
        for section, key, value in variants:
            old = self.info['data'][section][key]
            self.info['data'][section][key] = value
            with self.assertRaises(EvidenceError):
                self.run_smoke()
            self.assertEqual(self.calls[-1], ('connection_info', {}))
            self.info['data'][section][key] = old
        self.session.denied.assert_not_called()

    def test_broader_or_different_authority_prevents_a_denied_probe(self):
        for scopes in [[], ['contacts.read', 'templates.read'], ['templates.read']]:
            self.info['data']['connection']['effective_scopes'] = scopes
            with self.assertRaises(EvidenceError):
                self.run_smoke()
        self.session.denied.assert_not_called()

    def test_wrong_owned_contact_is_not_accepted_and_permission_probe_is_not_run(self):
        self.contact['data']['contact']['id'] = 99
        with self.assertRaises(EvidenceError):
            self.run_smoke()
        self.session.denied.assert_not_called()


if __name__ == '__main__':
    unittest.main()
