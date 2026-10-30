"""Application-specific JSON-RPC checks using synthetic responses, not real profiles."""
import copy
import importlib
import json
from pathlib import Path
import unittest
from uuid import uuid4

ROOT = Path(__file__).resolve().parents[3]
INFO_KEY = 'io.modelcontextprotocol/serverInfo'
PROFILES = ['2025-11-25', '2026-07-28']


class ProtocolChecksTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_protocol')
        self.transport = importlib.import_module('profile_transport')
        self.receipts = importlib.import_module('profile_receipts')
        self.contract_bytes = (ROOT / 'docs/api/mcp-tools.json').read_bytes()
        self.contract = json.loads(self.contract_bytes)
        self.origin = 'https://localhost:18443'
        self.scopes = sorted({s for op in self.contract['operations'] for s in op['minimum_scopes']})
        self.checks = self.make_checks()

    def make_checks(self, profile='2026-07-28'):
        return self.core.ProtocolChecks(profile, self.origin, self.contract_bytes)

    def exchange(self, result=None, *, status=200, error=None, rpc_id=7, headers=None, root=None):
        if root is None:
            value = copy.deepcopy(result or {})
            value.setdefault('resultType', 'complete')
            value.setdefault('_meta', {INFO_KEY: {'name': 'Correo MCP', 'version': '1.0.0'}})
            root = {'jsonrpc': '2.0', 'id': rpc_id, 'result': value}
        raw = json.dumps(root).encode()
        h = {'Content-Type': 'application/json', 'Cache-Control': 'no-store'}
        h.update(headers or {})
        receipt = self.receipts.HttpReceipt('allowed_call', 'POST', status, 1, len(raw), error)
        return self.transport.HttpExchange(receipt, tuple(h.items()), raw)

    def tool(self, data=None, *, is_error=False, rpc_id=7, profile='2026-07-28'):
        dto = data if data is not None else {'data': {'fixture_id': 73}, 'meta': {'trace_id': str(uuid4())}}
        result = {'isError': is_error, 'content': [{'type': 'text', 'text': json.dumps(dto)}]}
        if not is_error:
            result['structuredContent'] = dto
        return self.exchange(result, rpc_id=rpc_id)

    def forbidden(self, **changes):
        error = {'operation': 'contacts_create', 'code': 'forbidden',
                 'message': 'Operation not permitted.', 'outcome': 'not_applied',
                 'can_repeat_operation': False, 'recovery': 'none'}
        error.update(changes)
        return self.tool({'error': error, 'meta': {'trace_id': str(uuid4()),
                         'observed_at': '2026-10-27T00:00:00Z'}}, is_error=True)

    def definitions(self):
        fields = {'name', 'description', 'inputSchema', 'outputSchema', 'annotations'}
        return sorted([{k: copy.deepcopy(v) for k, v in op.items() if k in fields}
                       for op in self.contract['operations']], key=lambda x: x['name'])

    def test_allowed_results_in_both_profiles_require_a_fixture_predicate(self):
        for profile in PROFILES:
            checks = self.make_checks(profile)
            proof = checks.allowed(self.tool(), 7, 'connection_info',
                                   validate_fixture=lambda dto: dto['data']['fixture_id'] == 73)
            self.assertEqual(proof.kind, 'allowed')
            self.assertIs(proof.summary()['profile_qualified'], False)

    def test_http200_with_tool_or_rpc_error_is_never_an_allowed_result(self):
        for response in [self.forbidden(), self.exchange(root={
                'jsonrpc': '2.0', 'id': 7, 'error': {'code': -32603, 'message': 'fixture-private'}})]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.allowed(response, 7, 'connection_info', validate_fixture=lambda _: True)

    def test_response_ids_are_type_strict_and_cannot_match_boolean_or_float(self):
        for rpc_id in [True, 7.0, '7', 8, None]:
            with self.subTest(id_kind=type(rpc_id).__name__):
                with self.assertRaises(self.receipts.EvidenceError):
                    self.checks.result(self.exchange(rpc_id=rpc_id), 7)

    def test_wrong_jsonrpc_or_ambiguous_envelopes_are_rejected(self):
        for root in [{'jsonrpc': '1.0', 'id': 7, 'result': {}},
                     {'jsonrpc': '2.0', 'id': 7, 'result': {}, 'error': {'code': -1}},
                     {'jsonrpc': '2.0', 'id': 7, 'error': {'code': -1}}]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.result(self.exchange(root=root), 7)

    def test_streaming_responses_or_missing_no_store_do_not_match_this_server(self):
        for headers in [{'Content-Type': 'text/event-stream'}, {'Cache-Control': 'private'}]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.result(self.exchange(headers=headers), 7)

    def test_partial_or_deferred_results_are_not_accepted_as_complete(self):
        for kind in ['partial', 'accepted', None]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.result(self.exchange({'resultType': kind}), 7)

    def test_mirrored_text_and_structured_data_must_match_with_strict_types(self):
        for mirrored in [{'data': {'fixture_id': 74}}, {'data': {'fixture_id': True}}]:
            response = self.tool()
            root = response.json_body()
            root['result']['content'][0]['text'] = json.dumps(mirrored)
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.allowed(self.exchange(root=root), 7, 'connection_info',
                                    validate_fixture=lambda _: True)

    def test_fixture_mismatch_or_validator_failure_cannot_be_ignored(self):
        def broken(_dto):
            raise ValueError('fixture-private-validator')
        for predicate in [lambda _: False, lambda _: 1, broken, None]:
            with self.assertRaises(self.receipts.EvidenceError) as caught:
                self.checks.allowed(self.tool(), 7, 'connection_info', validate_fixture=predicate)
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_forbidden_requires_the_exact_operation_and_no_effect(self):
        for profile in PROFILES:
            proof = self.make_checks(profile).forbidden(self.forbidden(), 7, 'contacts_create')
            self.assertEqual(proof.kind, 'forbidden')
            self.assertNotIn('trace_id', json.dumps(proof.summary()))
            self.assertIs(proof.summary()['profile_qualified'], False)

    def test_other_error_codes_uncertainty_or_repeats_are_not_permission_denials(self):
        for changes in [{'operation': 'contacts_update'}, {'code': 'validation_failed'},
                        {'outcome': 'unknown'}, {'can_repeat_operation': True},
                        {'message': 'fixture-private'}]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.forbidden(self.forbidden(**changes), 7, 'contacts_create')

    def test_unauthenticated_response_requires_the_bound_resource_challenge(self):
        root = {'jsonrpc': '2.0', 'id': None,
                'error': {'code': -32600, 'message': 'Authentication required'}}
        expected = 'Bearer resource_metadata="' + self.origin + '/.well-known/oauth-protected-resource"'
        response = self.exchange(root=root, status=401, error='http_error',
                                 headers={'WWW-Authenticate': expected})
        self.assertEqual(self.checks.unauthenticated(response, 7).kind, 'unauthenticated')
        for challenge in ['', 'Bearer resource_metadata="https://elsewhere.example/.well-known/oauth-protected-resource"']:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.unauthenticated(self.exchange(root=root, status=401, error='http_error',
                                                        headers={'WWW-Authenticate': challenge}), 7)

    def test_initialize_is_legacy_only_and_session_identifiers_stay_private(self):
        session = str(uuid4())
        response = self.exchange({'protocolVersion': PROFILES[0], 'capabilities': {},
                                  'serverInfo': {'name': 'Correo MCP', 'version': '1.0.0'}},
                                 headers={'MCP-Session-Id': session})
        proof = self.make_checks(PROFILES[0]).initialized(response, 7)
        self.assertEqual(proof.session_id, session)
        self.assertNotIn(session, repr(proof))
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks.initialized(response, 7)

    def test_modern_discovery_requires_both_versions_and_no_session_downgrade(self):
        response = self.exchange({'supportedVersions': PROFILES, 'capabilities': {}})
        self.assertEqual(self.checks.discovered(response, 7).kind, 'discovered')
        for value in [self.exchange({'supportedVersions': [PROFILES[1]], 'capabilities': {}}),
                      self.exchange({'supportedVersions': PROFILES, 'capabilities': {}},
                                    headers={'MCP-Session-Id': str(uuid4())})]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.checks.discovered(value, 7)

    def test_initialized_notification_requires_legacy_202_and_no_body(self):
        receipt = self.receipts.HttpReceipt('initialized_notification', 'POST', 202, 1, 0)
        response = self.transport.HttpExchange(receipt, (('Cache-Control', 'no-store'),), b'')
        legacy = self.make_checks(PROFILES[0])
        self.assertEqual(legacy.notification(response).kind, 'initialized_notification')
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks.notification(response)
        with self.assertRaises(self.receipts.EvidenceError):
            legacy.notification(self.transport.HttpExchange(receipt, response.headers, b'{}'))

    def test_registry_collects_all37_with_exact_contract_and_filters_empty_scope(self):
        definitions = self.definitions()
        self.assertEqual(len(definitions), 37)
        registry = self.checks.registry(self.scopes)
        cursor = registry.consume(self.exchange({'tools': definitions[:25], 'nextCursor': 'fixture-next'}), 7)
        self.assertEqual(cursor, 'fixture-next')
        self.assertIsNone(registry.consume(self.exchange({'tools': definitions[25:]}), 7))
        self.assertEqual(registry.finish()['tools_verified'], 37)
        restricted = self.checks.registry([])
        selected = [item for item in definitions if item['name'] == 'connection_info']
        restricted.consume(self.exchange({'tools': selected}), 7)
        self.assertEqual(restricted.finish()['tools_verified'], 1)

    def test_registry_cannot_finish_without_all_expected_tools_or_a_final_page(self):
        definitions = self.definitions()
        registry = self.checks.registry(self.scopes)
        registry.consume(self.exchange({'tools': definitions[:10], 'nextCursor': 'fixture-next'}), 7)
        with self.assertRaises(self.receipts.EvidenceError):
            registry.finish()
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks.registry(self.scopes).consume(self.exchange({'tools': definitions[:10]}), 7)

    def test_registry_rejects_schema_drift_unknown_or_disallowed_operations(self):
        for mode in ['schema', 'unknown', 'scope']:
            definitions = self.definitions()
            registry = self.checks.registry([] if mode == 'scope' else self.scopes)
            if mode == 'schema':
                definitions[0]['annotations']['readOnlyHint'] = not definitions[0]['annotations']['readOnlyHint']
            elif mode == 'unknown':
                definitions[0]['name'] = 'fixture_private_unknown'
            with self.assertRaises(self.receipts.EvidenceError):
                registry.consume(self.exchange({'tools': definitions}), 7)

    def test_registry_rejects_duplicate_tools_and_repeated_cursors(self):
        definitions = self.definitions()
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks.registry(self.scopes).consume(self.exchange({'tools': [definitions[0], definitions[0]]}), 7)
        registry = self.checks.registry(self.scopes)
        registry.consume(self.exchange({'tools': definitions[:10], 'nextCursor': 'fixture-next'}), 7)
        with self.assertRaises(self.receipts.EvidenceError):
            registry.consume(self.exchange({'tools': definitions[10:20], 'nextCursor': 'fixture-next'}), 7)

    def test_invalid_profile_or_contract_has_no_secret_diagnostic_channel(self):
        with self.assertRaises(self.receipts.EvidenceError):
            self.make_checks('fixture-private-profile')
        with self.assertRaises(self.receipts.EvidenceError) as caught:
            self.core.ProtocolChecks(PROFILES[1], self.origin, b'{"fixture-private":"value"}')
        self.assertNotIn('fixture-private', str(caught.exception))

    def test_validated_dto_stays_private_and_mismatch_diagnostics_never_echo_it(self):
        private = {'data': {'name': 'fixture-private-person'}, 'meta': {'trace_id': str(uuid4())}}
        proof = self.checks.allowed(self.tool(private), 7, 'connection_info', validate_fixture=lambda _: True)
        self.assertEqual(proof.payload, private)
        self.assertNotIn('fixture-private-person', repr(proof))
        self.assertNotIn('fixture-private-person', json.dumps(proof.summary()))
        self.assertIs(proof.summary()['profile_qualified'], False)


    def test_surrogate_request_id_has_only_a_bounded_diagnostic(self):
        with self.assertRaises(self.receipts.EvidenceError):
            self.checks.result(self.exchange(), 'fixture-private-\ud800')

    def test_surrogate_cursor_has_only_a_bounded_diagnostic(self):
        registry = self.checks.registry(self.scopes)
        with self.assertRaises(self.receipts.EvidenceError):
            registry.consume(self.exchange({'tools': self.definitions()[:10], 'nextCursor': '\ud800'}), 7)



if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(ProtocolChecksTests)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    print(json.dumps({'tests': result.testsRun, 'failures': len(result.failures),
                      'errors': len(result.errors), 'skipped': len(result.skipped),
                      'classification': 'synthetic protocol cases, not deployed profile or native-client evidence'}))
    raise SystemExit(0 if result.wasSuccessful() else 1)
