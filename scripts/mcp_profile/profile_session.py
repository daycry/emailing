"""One owned MCP conversation. HTTP/protocol checks do not qualify a deployment."""
import json
import math
import time

from profile_receipts import EvidenceError, HttpReceipt
from profile_schema import SchemaBoundChecks
from profile_transport import HttpExchange


class ProtocolSession:
    def __init__(self, profile, issuer, contract_bytes, *, token, request, record,
                 schema_validator, budget_seconds=300, clock=time.monotonic):
        self._checks = SchemaBoundChecks(profile, issuer, contract_bytes,
                                        schema_validator=schema_validator)
        if (type(token) is not str or not 1 <= len(token) <= 4096
                or any(ord(char) < 33 or ord(char) >= 127 for char in token)):
            raise EvidenceError('invalid_credentials')
        if not callable(request) or not callable(record) or not callable(clock):
            raise EvidenceError('invalid_session_config')
        if (type(budget_seconds) not in (float, int) or not math.isfinite(budget_seconds)
                or not 0 < budget_seconds <= 3600):
            raise EvidenceError('invalid_session_config')
        self._token = token
        self._request, self._record = request, record
        self._clock = clock
        self._deadline = clock() + budget_seconds
        self._state = 'new'
        self._session_id = None
        self._next_id = 1
        self._sent = 0

    def summary(self):
        return {'protocol': self._checks.profile, 'state': self._state,
                'requests_sent': self._sent, 'profile_qualified': False,
                'native_client_qualified': False}

    def _ready(self):
        if self._state != 'ready':
            raise EvidenceError('session_not_ready')

    def _rpc(self, operation, method, params, *, notification=False):
        if self._clock() >= self._deadline:
            self._state = 'failed'
            raise EvidenceError('phase_deadline')
        headers = {'Content-Type': 'application/json', 'Authorization': 'Bearer ' + self._token}
        params = dict(params)
        if self._checks.profile == '2026-07-28':
            params['_meta'] = {'io.modelcontextprotocol/protocolVersion': self._checks.profile,
                               'io.modelcontextprotocol/clientCapabilities': {}}
            headers.update({'MCP-Protocol-Version': self._checks.profile, 'Mcp-Method': method})
            if method == 'tools/call':
                headers['Mcp-Name'] = params['name']
        elif method != 'initialize':
            headers.update({'MCP-Protocol-Version': self._checks.profile,
                            'MCP-Session-Id': self._session_id})
        document = {'jsonrpc': '2.0', 'method': method, 'params': params}
        rpc_id = None if notification else self._next_id
        if not notification:
            document['id'] = rpc_id
        try:
            body = json.dumps(document, ensure_ascii=False, allow_nan=False).encode('utf-8')
        except (ValueError, TypeError, UnicodeError, RecursionError):
            raise EvidenceError('invalid_rpc_input') from None
        if len(body) > 8388608:
            raise EvidenceError('invalid_rpc_input')
        if not notification:
            self._next_id += 1
        self._sent += 1
        try:
            exchange = self._request(operation, 'POST', self._checks.issuer + '/mcp',
                                     headers=headers, body=body)
            if (type(exchange) is not HttpExchange or type(exchange.receipt) is not HttpReceipt
                    or exchange.receipt.operation != operation or exchange.receipt.method != 'POST'):
                raise EvidenceError('invalid_transport_observation')
            self._record(exchange.receipt)
            if self._clock() >= self._deadline:
                raise EvidenceError('phase_deadline')
            return exchange, rpc_id
        except Exception:
            self._state = 'failed'
            raise EvidenceError('session_exchange_failed') from None

    def open(self):
        if self._state != 'new':
            raise EvidenceError('session_already_started')
        self._state = 'opening'
        try:
            if self._checks.profile == '2025-11-25':
                exchange, rpc_id = self._rpc('initialize', 'initialize', {
                    'protocolVersion': self._checks.profile, 'capabilities': {},
                    'clientInfo': {'name': 'Correo profile probe', 'version': '1.0.0'}})
                proof = self._checks.initialized(exchange, rpc_id)
                self._session_id = proof.session_id
                exchange, _ = self._rpc('initialized_notification', 'notifications/initialized', {},
                                        notification=True)
                self._checks.notification(exchange)
            else:
                exchange, rpc_id = self._rpc('server_discover', 'server/discover', {})
                proof = self._checks.discovered(exchange, rpc_id)
            self._state = 'ready'
            return proof
        except Exception:
            self._state = 'failed'
            raise EvidenceError('handshake_failed') from None

    def registry(self, effective_scopes):
        self._ready()
        registry = self._checks.registry(effective_scopes)
        cursor = None
        try:
            while True:
                params = {'per_page': 25}
                if cursor is not None:
                    params['cursor'] = cursor
                exchange, rpc_id = self._rpc('tools_list', 'tools/list', params)
                cursor = registry.consume(exchange, rpc_id)
                if cursor is None:
                    return registry.finish()
        except Exception:
            self._state = 'failed'
            raise EvidenceError('registry_failed') from None

    def _tool(self, operation, name, arguments, validator):
        self._ready()
        self._checks._name(name)
        if type(arguments) is not dict:
            raise EvidenceError('invalid_arguments')
        exchange, rpc_id = self._rpc(operation, 'tools/call', {'name': name, 'arguments': arguments})
        try:
            return validator(exchange, rpc_id)
        except Exception:
            self._state = 'failed'
            raise EvidenceError('tool_check_failed') from None

    def allowed(self, name, arguments, *, validate_fixture):
        if not callable(validate_fixture):
            raise EvidenceError('missing_fixture_validator')
        return self._tool('allowed_call', name, arguments,
                          lambda exchange, rpc_id: self._checks.allowed(
                              exchange, rpc_id, name, validate_fixture=validate_fixture))

    def denied(self, name, arguments):
        return self._tool('denied_call', name, arguments,
                          lambda exchange, rpc_id: self._checks.forbidden(exchange, rpc_id, name))

    def rejected(self, operation):
        if operation not in ('revoked_call', 'expired_call'):
            raise EvidenceError('invalid_authentication_probe')
        try:
            return self._tool(operation, 'connection_info', {}, self._checks.unauthenticated)
        finally:
            self._state = 'closed'
            self._session_id = None
            self._token = None
