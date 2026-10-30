"""Request flow tests with an explicit synthetic HTTP boundary."""
import importlib
import json
from pathlib import Path
import unittest
from uuid import uuid4

from profile_protocol import INFO_KEY, SERVER_INFO
from profile_receipts import EvidenceError, HttpReceipt
from profile_transport import HttpExchange

ROOT = Path(__file__).resolve().parents[3]
ORIGIN = 'https://localhost:18443'
TOKEN = 'fixture-private-token'


class ProfileSessionTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_session')
        self.contract = (ROOT / 'docs/api/mcp-tools.json').read_bytes()
        self.calls = []
        self.receipts = []
        self.replies = []
        self.session_id = str(uuid4())

    def reply(self, operation, rpc_id=1, result=None, *, status=200, headers=(), error=None):
        payload = {'resultType': 'complete', '_meta': {INFO_KEY: SERVER_INFO}, **(result or {})}
        body = json.dumps({'jsonrpc': '2.0', 'id': rpc_id, 'result': payload}).encode()
        if status == 202:
            body = b''
        elif status == 401:
            body = json.dumps({'jsonrpc': '2.0', 'id': None,
                               'error': {'code': -32600, 'message': 'Authentication required'}}).encode()
        receipt = HttpReceipt(operation, 'POST', status, 1, len(body), error)
        return HttpExchange(receipt, (('Content-Type', 'application/json'),
                                      ('Cache-Control', 'no-store'), *headers), body)

    def request(self, operation, method, url, **kwargs):
        self.calls.append((operation, method, url, kwargs))
        return self.replies.pop(0)

    def session(self, profile='2026-07-28', token=TOKEN, **kwargs):
        return self.core.ProtocolSession(profile, ORIGIN, self.contract, token=token,
                                         request=self.request, record=self.receipts.append,
                                         schema_validator=lambda name, dto: True, **kwargs)

    def opened(self, **kwargs):
        session = self.session(**kwargs)
        self.replies.append(self.reply('server_discover', result={
            'supportedVersions': ['2025-11-25', '2026-07-28'], 'capabilities': {}}))
        session.open()
        return session

    def tool_reply(self, operation='allowed_call', rpc_id=2, dto=None, error=False):
        dto = dto or {'data': {'fixture': 73}}
        result = {'isError': error, 'content': [{'type': 'text', 'text': json.dumps(dto)}]}
        if not error:
            result['structuredContent'] = dto
        return self.reply(operation, rpc_id, result)

    def test_modern_handshake_mirrors_metadata_without_session_or_initialize(self):
        session = self.opened()
        operation, method, url, kwargs = self.calls[0]
        body = json.loads(kwargs['body'])
        self.assertEqual((operation, method, url), ('server_discover', 'POST', ORIGIN + '/mcp'))
        self.assertEqual(body['method'], 'server/discover')
        self.assertEqual(body['params']['_meta'], {
            'io.modelcontextprotocol/protocolVersion': '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities': {}})
        self.assertEqual(kwargs['headers']['Mcp-Method'], 'server/discover')
        self.assertNotIn('MCP-Session-Id', kwargs['headers'])
        self.assertIs(session.summary()['profile_qualified'], False)

    def test_legacy_handshake_notifies_and_retains_only_its_owned_session(self):
        session = self.session('2025-11-25')
        self.replies.extend([
            self.reply('initialize', result={'protocolVersion': '2025-11-25',
                       'serverInfo': SERVER_INFO, 'capabilities': {}},
                       headers=(('MCP-Session-Id', self.session_id),)),
            self.reply('initialized_notification', status=202)])
        session.open()
        self.assertEqual(len(self.calls), 2)
        body = json.loads(self.calls[0][3]['body'])
        self.assertEqual(body['method'], 'initialize')
        self.assertEqual(body['params']['protocolVersion'], '2025-11-25')
        notification = json.loads(self.calls[1][3]['body'])
        self.assertNotIn('id', notification)
        self.assertEqual(notification['method'], 'notifications/initialized')
        self.assertEqual(self.calls[1][3]['headers']['MCP-Session-Id'], self.session_id)

    def test_tool_call_before_handshake_never_touches_transport(self):
        with self.assertRaises(EvidenceError):
            self.session().allowed('contacts_get', {'id': 73}, validate_fixture=lambda _: True)
        self.assertEqual(self.calls, [])

    def test_allowed_request_keeps_arguments_and_validates_fixture_once(self):
        session = self.opened()
        self.replies.append(self.tool_reply())
        seen = []
        proof = session.allowed('contacts_get', {'id': 73},
                                validate_fixture=lambda dto: seen.append(dto) is None)
        self.assertEqual(proof.kind, 'allowed')
        self.assertEqual(len(seen), 1)
        body = json.loads(self.calls[-1][3]['body'])
        self.assertEqual(body['params']['arguments'], {'id': 73})
        self.assertEqual(self.calls[-1][3]['headers']['Mcp-Name'], 'contacts_get')
        self.assertEqual(self.calls[-1][3]['headers']['Authorization'], 'Bearer ' + TOKEN)

    def test_denied_request_requires_an_actual_permission_error(self):
        session = self.opened()
        self.replies.append(self.tool_reply('denied_call', dto={'error': {
            'operation': 'contacts_delete', 'code': 'forbidden',
            'message': 'Operation not permitted.', 'outcome': 'not_applied',
            'can_repeat_operation': False, 'recovery': 'none'}}, error=True))
        self.assertEqual(session.denied('contacts_delete', {'id': 73}).kind, 'forbidden')

    def test_transport_exception_is_sanitized_without_retrying_a_tool(self):
        session = self.opened()
        def broken(*args, **kwargs):
            self.calls.append(('failed',))
            raise OSError('fixture-private-endpoint')
        session._request = broken
        with self.assertRaises(EvidenceError) as caught:
            session.allowed('contacts_delete', {'id': 73}, validate_fixture=lambda _: True)
        self.assertNotIn('fixture-private', str(caught.exception))
        self.assertEqual(len(self.calls), 2)

    def test_invalid_arguments_or_unknown_operation_are_rejected_before_http(self):
        session = self.opened()
        for name, args in [('fixture-private-name', {}), ('contacts_get', []),
                           ('contacts_get', {'value': float('nan')})]:
            with self.assertRaises(EvidenceError):
                session.allowed(name, args, validate_fixture=lambda _: True)
        self.assertEqual(len(self.calls), 1)

    def test_http_observation_is_recorded_even_when_protocol_validation_fails(self):
        session = self.session()
        self.replies.append(self.reply('server_discover', result={'capabilities': {}}))
        with self.assertRaises(EvidenceError):
            session.open()
        self.assertEqual(len(self.receipts), 1)
        self.assertIsInstance(self.receipts[0], HttpReceipt)
        with self.assertRaises(EvidenceError):
            session.open()
        self.assertEqual(len(self.calls), 1)

    def test_summary_and_representation_do_not_include_credentials_or_body(self):
        session = self.opened()
        self.assertNotIn('fixture-private', repr(session))
        self.assertNotIn('fixture-private', json.dumps(session.summary()))
        self.assertNotIn('fixture-private', repr(self.receipts))

    def test_registry_reads_every_page_with_an_opaque_cursor(self):
        session = self.opened()
        operations = json.loads(self.contract)['operations']
        names = sorted([op for op in operations if not op['minimum_scopes']], key=lambda op: op['name'])
        tools = [{key: value for key, value in op.items()
                  if key in {'name', 'description', 'inputSchema', 'outputSchema', 'annotations'}}
                 for op in names]
        self.replies.append(self.reply('tools_list', 2, {'tools': tools}))
        result = session.registry([])
        self.assertEqual(result['tools_verified'], 1)
        self.assertEqual(json.loads(self.calls[-1][3]['body'])['method'], 'tools/list')

    def test_elapsed_phase_budget_prevents_starting_another_request(self):
        clock = [0.0]
        session = self.opened(clock=lambda: clock[0], budget_seconds=10)
        clock[0] = 11.0
        with self.assertRaises(EvidenceError):
            session.allowed('contacts_get', {'id': 73}, validate_fixture=lambda _: True)
        self.assertEqual(len(self.calls), 1)

    def test_invalid_credentials_are_rejected_without_http_or_echo(self):
        for token in ['', 'a\r\nb', '\ud800', True, 'a b']:
            with self.assertRaises(EvidenceError) as caught:
                self.session(token=token)
            self.assertNotIn('fixture-private', str(caught.exception))
        self.assertEqual(self.calls, [])

    def test_revoked_and_expired_checks_require_http401_with_bound_challenge(self):
        for operation in ['revoked_call', 'expired_call']:
            session = self.opened()
            self.replies.append(self.reply(operation, status=401, error='http_error',
                headers=(('WWW-Authenticate', 'Bearer resource_metadata="' + ORIGIN +
                          '/.well-known/oauth-protected-resource"'),)))
            self.assertEqual(session.rejected(operation).kind, 'unauthenticated')


if __name__ == '__main__':
    unittest.main()
