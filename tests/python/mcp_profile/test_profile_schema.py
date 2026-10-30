"""Pure schema validation with the real PHP validator; no Laravel boot or database."""
import copy
import importlib
import json
from pathlib import Path
import subprocess
import unittest
from unittest.mock import Mock
from uuid import uuid4

ROOT = Path(__file__).resolve().parents[3]


class SchemaBridgeTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_schema')
        self.receipts = importlib.import_module('profile_receipts')
        self.transport = importlib.import_module('profile_transport')
        self.validator = self.core.PhpSchemaValidator(ROOT)
        self.contract = (ROOT / 'docs/api/mcp-tools.json').read_bytes()

    def dto(self):
        return {'data': {'id': 73, 'deleted': True},
                'meta': {'trace_id': str(uuid4()), 'observed_at': '2026-10-27T00:00:00Z'}}

    def exchange(self, dto):
        result = {'resultType': 'complete', 'isError': False,
                  '_meta': {'io.modelcontextprotocol/serverInfo': {'name': 'Correo MCP', 'version': '1.0.0'}},
                  'content': [{'type': 'text', 'text': json.dumps(dto)}],
                  'structuredContent': dto}
        body = json.dumps({'jsonrpc': '2.0', 'id': 7, 'result': result}).encode()
        receipt = self.receipts.HttpReceipt('allowed_call', 'POST', 200, 1, len(body))
        return self.transport.HttpExchange(receipt, (
            ('Content-Type', 'application/json'), ('Cache-Control', 'no-store')), body)

    def checks(self, validator=None):
        return self.core.SchemaBoundChecks('2026-07-28', 'https://localhost:18443',
                                          self.contract, schema_validator=validator or self.validator)

    def test_real_application_output_schema_accepts_a_valid_dto(self):
        self.assertIs(self.validator('contacts_delete', self.dto()), True)

    def test_real_schema_rejects_wrong_types_extra_fields_and_invalid_metadata(self):
        variants = []
        for change in [('id', True), ('deleted', False), ('fixture_private', 'value')]:
            value = self.dto()
            value['data'][change[0]] = change[1]
            variants.append(value)
        value = self.dto()
        value['meta']['trace_id'] = 'invalid-uuid'
        variants.append(value)
        for value in variants:
            with self.subTest(kind='invalid DTO'):
                self.assertIs(self.validator('contacts_delete', value), False)

    def test_real_conditional_schema_checks_pagination_invariants(self):
        value = {'data': {'items': []}, 'meta': self.dto()['meta'],
                 'page': {'has_more': False, 'next_cursor': None}}
        self.assertIs(self.validator('lists_list', value), True)
        value['page'] = {'has_more': True, 'next_cursor': 'fixture-cursor-at-least-16'}
        self.assertIs(self.validator('lists_list', value), False)

    def test_unknown_operation_and_unencodable_value_have_bounded_diagnostics(self):
        for name, value in [('fixture-private-operation', self.dto()),
                            ('contacts_delete', {'data': '\ud800'})]:
            with self.assertRaises(self.receipts.EvidenceError) as caught:
                self.validator(name, value)
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_schema_mismatch_is_checked_before_the_owned_fixture_predicate(self):
        checks = self.checks()
        value = self.dto()
        value['data']['deleted'] = False
        predicate = Mock(return_value=True)
        with self.assertRaises(self.receipts.EvidenceError):
            checks.allowed(self.exchange(value), 7, 'contacts_delete', validate_fixture=predicate)
        predicate.assert_not_called()

    def test_valid_schema_and_fixture_produce_only_a_partial_nonqualifying_step(self):
        proof = self.checks().allowed(self.exchange(self.dto()), 7, 'contacts_delete',
                                     validate_fixture=lambda dto: dto['data']['id'] == 73)
        self.assertEqual(proof.kind, 'allowed')
        self.assertIs(proof.summary()['profile_qualified'], False)
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks().allowed(self.exchange(self.dto()), 7, 'contacts_delete',
                                 validate_fixture=lambda _: False)

    def test_validator_unavailability_or_nonboolean_success_cannot_be_ignored(self):
        def broken(_name, _value):
            raise OSError('fixture-private-engine-path')
        for validator in [broken, lambda _name, _value: 1]:
            with self.assertRaises(self.receipts.EvidenceError) as caught:
                self.checks(validator).allowed(self.exchange(self.dto()), 7, 'contacts_delete',
                                               validate_fixture=lambda _: True)
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_bridge_uses_stdin_and_returns_no_input_data_or_diagnostics(self):
        payload = b'{"operation":"contacts_delete","value":{"fixture-private":"value"}}'
        command = [self.validator.php_binary, '-d', 'zend.exception_ignore_args=1',
                   str(Path(self.core.__file__).with_name('profile_schema_bridge.php'))]
        result = subprocess.run(command, cwd=ROOT, input=payload, capture_output=True, timeout=30)
        self.assertEqual(result.returncode, 0)
        self.assertEqual(json.loads(result.stdout), {'available': True, 'valid': False})
        self.assertEqual(result.stderr, b'')
        self.assertNotIn(b'fixture-private', result.stdout)
        self.assertNotIn(payload.decode(), ' '.join(command))

    def test_malformed_bridge_input_does_not_echo_it_or_boot_the_application(self):
        command = [self.validator.php_binary, '-d', 'zend.exception_ignore_args=1',
                   str(Path(self.core.__file__).with_name('profile_schema_bridge.php'))]
        result = subprocess.run(command, cwd=ROOT, input=b'fixture-private-invalid-json',
                                capture_output=True, timeout=30)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(json.loads(result.stdout), {'available': False, 'valid': False})
        self.assertEqual(result.stderr, b'')
        self.assertNotIn(b'fixture-private', result.stdout)


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(SchemaBridgeTests)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    print(json.dumps({'tests': result.testsRun, 'failures': len(result.failures),
                      'errors': len(result.errors), 'skipped': len(result.skipped),
                      'classification': 'pure schema bridge tests, no application/database/network qualification'}))
    raise SystemExit(0 if result.wasSuccessful() else 1)
