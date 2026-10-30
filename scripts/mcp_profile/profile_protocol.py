"""Partial protocol assertions for this application; never deployment/client qualification."""
from dataclasses import dataclass, field
from hashlib import sha256
from uuid import UUID

from profile_receipts import EvidenceError
from profile_transport import decode_json_bytes, validated_origin

PROFILES = frozenset({'2025-11-25', '2026-07-28'})
INFO_KEY = 'io.modelcontextprotocol/serverInfo'
SERVER_INFO = {'name': 'Correo MCP', 'version': '1.0.0'}
DEFINITION_KEYS = frozenset({'name', 'description', 'inputSchema', 'outputSchema', 'annotations'})
MAX_BODY = 8388608


def _same(left, right):
    if type(left) is not type(right):
        return False
    if type(left) is dict:
        return left.keys() == right.keys() and all(_same(left[key], right[key]) for key in left)
    if type(left) is list:
        return len(left) == len(right) and all(_same(a, b) for a, b in zip(left, right))
    return left == right


def _id(value):
    if type(value) is int:
        return abs(value) <= 9007199254740991
    if type(value) is not str:
        return False
    try:
        return len(value.encode('utf-8')) <= 128
    except UnicodeError:
        return False


def _header(exchange, name, required=True):
    values = []
    for pair in exchange.headers:
        if type(pair) not in (tuple, list) or len(pair) != 2 or any(type(item) is not str for item in pair):
            raise EvidenceError('invalid_response_header')
        if pair[0].lower() == name.lower():
            values.append(pair[1])
    if len(values) > 1 or (required and len(values) != 1):
        raise EvidenceError('invalid_response_header')
    return values[0] if values else None


@dataclass(frozen=True, slots=True)
class StepProof:
    kind: str
    profile: str
    operation: str | None = None
    payload: dict | None = field(default=None, repr=False)
    session_id: str | None = field(default=None, repr=False)

    def summary(self):
        return {'kind': self.kind, 'protocol': self.profile, 'operation': self.operation,
                'profile_qualified': False}


class ProtocolChecks:
    def __init__(self, profile, issuer, contract_bytes):
        if type(profile) is not str or profile not in PROFILES:
            raise EvidenceError('invalid_profile')
        validated_origin(issuer)
        try:
            contract = decode_json_bytes(contract_bytes)
            operations = contract['operations']
            if type(operations) is not list or len(operations) != 37:
                raise EvidenceError('invalid_contract')
            index = {}
            scopes = set()
            for operation in operations:
                if type(operation) is not dict or type(operation.get('name')) is not str:
                    raise EvidenceError('invalid_contract')
                name = operation['name']
                if name in index or not (DEFINITION_KEYS - {'description'}).issubset(operation):
                    raise EvidenceError('invalid_contract')
                minimum = operation['minimum_scopes']
                if type(minimum) is not list or any(type(value) is not str for value in minimum):
                    raise EvidenceError('invalid_contract')
                scopes.update(minimum)
                index[name] = operation
        except (EvidenceError, KeyError, TypeError):
            raise EvidenceError('invalid_contract') from None
        self.profile = profile
        self.issuer = issuer
        self._operations = index
        self._scopes = scopes
        self.contract_sha256 = sha256(contract_bytes).hexdigest()

    def _name(self, name):
        if type(name) is not str or name not in self._operations:
            raise EvidenceError('invalid_operation')

    def _no_store(self, exchange):
        value = _header(exchange, 'Cache-Control')
        if 'no-store' not in {part.strip().lower() for part in value.split(',')}:
            raise EvidenceError('missing_no_store')

    def _document(self, exchange):
        self._no_store(exchange)
        content_type = _header(exchange, 'Content-Type').split(';', 1)[0].strip().lower()
        if content_type != 'application/json' or type(exchange.body) is not bytes or len(exchange.body) > MAX_BODY:
            raise EvidenceError('invalid_response')
        document = exchange.json_body()
        if type(document) is not dict or document.get('jsonrpc') != '2.0':
            raise EvidenceError('invalid_envelope')
        if ('result' in document) == ('error' in document):
            raise EvidenceError('invalid_envelope')
        return document

    def result(self, exchange, expected_id):
        if not _id(expected_id) or exchange.receipt.status != 200 or exchange.receipt.error is not None:
            raise EvidenceError('unexpected_response')
        document = self._document(exchange)
        if not _same(document.get('id'), expected_id) or 'error' in document:
            raise EvidenceError('rpc_mismatch')
        result = document.get('result')
        if type(result) is not dict or result.get('resultType') != 'complete':
            raise EvidenceError('incomplete_result')
        meta = result.get('_meta')
        if type(meta) is not dict or not _same(meta.get(INFO_KEY), SERVER_INFO):
            raise EvidenceError('server_identity_mismatch')
        if self.profile == '2026-07-28' and _header(exchange, 'MCP-Session-Id', required=False) is not None:
            raise EvidenceError('unexpected_session')
        return result

    def _tool(self, exchange, expected_id):
        result = self.result(exchange, expected_id)
        content = result.get('content')
        if (type(result.get('isError')) is not bool or type(content) is not list or len(content) != 1
                or type(content[0]) is not dict or content[0].get('type') != 'text'
                or type(content[0].get('text')) is not str):
            raise EvidenceError('invalid_tool_result')
        try:
            dto = decode_json_bytes(content[0]['text'].encode('utf-8'))
        except UnicodeError:
            raise EvidenceError('invalid_tool_result') from None
        if type(dto) is not dict:
            raise EvidenceError('invalid_tool_result')
        return result, dto

    def allowed(self, exchange, expected_id, name, *, validate_fixture):
        self._name(name)
        if not callable(validate_fixture):
            raise EvidenceError('missing_fixture_validator')
        result, dto = self._tool(exchange, expected_id)
        if result['isError'] or not _same(result.get('structuredContent'), dto):
            raise EvidenceError('unexpected_tool_error')
        try:
            matched = validate_fixture(dto)
        except Exception:
            raise EvidenceError('fixture_mismatch') from None
        if matched is not True:
            raise EvidenceError('fixture_mismatch')
        return StepProof('allowed', self.profile, name, payload=dto)

    def forbidden(self, exchange, expected_id, name):
        self._name(name)
        result, dto = self._tool(exchange, expected_id)
        error = dto.get('error')
        if (result['isError'] is not True or 'structuredContent' in result or type(error) is not dict
                or error.get('operation') != name or error.get('code') != 'forbidden'
                or error.get('message') != 'Operation not permitted.'
                or error.get('outcome') != 'not_applied' or error.get('can_repeat_operation') is not False
                or error.get('recovery') != 'none'):
            raise EvidenceError('not_a_permission_denial')
        return StepProof('forbidden', self.profile, name)

    def unauthenticated(self, exchange, expected_id):
        if not _id(expected_id) or exchange.receipt.status != 401 or exchange.receipt.error != 'http_error':
            raise EvidenceError('not_an_authentication_rejection')
        document = self._document(exchange)
        error = document.get('error')
        response_id = document.get('id')
        if (response_id is not None and not _same(response_id, expected_id)) or type(error) is not dict:
            raise EvidenceError('not_an_authentication_rejection')
        if error.get('code') != -32600 or error.get('message') not in ['Authentication required', 'Unauthorized']:
            raise EvidenceError('not_an_authentication_rejection')
        expected = 'Bearer resource_metadata="' + self.issuer + '/.well-known/oauth-protected-resource"'
        if _header(exchange, 'WWW-Authenticate') != expected:
            raise EvidenceError('resource_challenge_mismatch')
        return StepProof('unauthenticated', self.profile)

    def initialized(self, exchange, expected_id):
        if self.profile != '2025-11-25':
            raise EvidenceError('wrong_handshake')
        result = self.result(exchange, expected_id)
        session = _header(exchange, 'MCP-Session-Id')
        try:
            parsed = UUID(session)
        except (ValueError, TypeError, AttributeError):
            raise EvidenceError('invalid_session') from None
        if (str(parsed) != session or result.get('protocolVersion') != self.profile
                or type(result.get('capabilities')) is not dict
                or not _same(result.get('serverInfo'), SERVER_INFO)):
            raise EvidenceError('invalid_initialize')
        return StepProof('initialized', self.profile, session_id=session)

    def discovered(self, exchange, expected_id):
        if self.profile != '2026-07-28':
            raise EvidenceError('wrong_handshake')
        result = self.result(exchange, expected_id)
        versions = result.get('supportedVersions')
        if (type(versions) is not list or any(type(value) is not str for value in versions)
                or len(versions) != 2 or set(versions) != PROFILES
                or type(result.get('capabilities')) is not dict):
            raise EvidenceError('invalid_discovery')
        return StepProof('discovered', self.profile)

    def notification(self, exchange):
        self._no_store(exchange)
        if (self.profile != '2025-11-25' or exchange.receipt.status != 202
                or exchange.receipt.error is not None or exchange.body != b''):
            raise EvidenceError('invalid_notification')
        return StepProof('initialized_notification', self.profile)

    def registry(self, effective_scopes):
        return RegistryCheck(self, effective_scopes)


class RegistryCheck:
    def __init__(self, checks, effective_scopes):
        if (type(effective_scopes) not in (list, tuple)
                or any(type(value) is not str or value not in checks._scopes for value in effective_scopes)):
            raise EvidenceError('invalid_effective_scopes')
        scopes = set(effective_scopes)
        self._checks = checks
        self._expected = {name for name, operation in checks._operations.items()
                          if set(operation['minimum_scopes']) <= scopes}
        self._seen = set()
        self._cursors = set()
        self._last = None
        self._pages = 0
        self._finished = False

    def consume(self, exchange, expected_id):
        if self._finished:
            raise EvidenceError('registry_already_complete')
        result = self._checks.result(exchange, expected_id)
        tools = result.get('tools')
        if type(tools) is not list:
            raise EvidenceError('invalid_registry_page')
        names = []
        for tool in tools:
            if type(tool) is not dict or type(tool.get('name')) is not str:
                raise EvidenceError('invalid_registry_page')
            name = tool['name']
            if name not in self._expected:
                raise EvidenceError('unexpected_registry_operation')
            expected = {key: value for key, value in self._checks._operations[name].items()
                        if key in DEFINITION_KEYS}
            if not _same(tool, expected):
                raise EvidenceError('registry_definition_mismatch')
            names.append(name)
        if (names != sorted(names) or len(names) != len(set(names)) or self._seen.intersection(names)
                or (names and self._last is not None and names[0] <= self._last)):
            raise EvidenceError('registry_order_or_duplicate')
        seen = self._seen | set(names)
        cursor = result.get('nextCursor')
        if cursor is not None:
            if (type(cursor) is not str or not cursor or len(cursor) > 2048
                    or any(ord(char) < 33 or ord(char) > 126 for char in cursor)
                    or cursor in self._cursors or not names or seen == self._expected):
                raise EvidenceError('invalid_registry_cursor')
        elif seen != self._expected:
            raise EvidenceError('incomplete_registry')
        self._seen = seen
        self._pages += 1
        self._last = names[-1] if names else self._last
        if cursor is None:
            self._finished = True
        else:
            self._cursors.add(cursor)
        return cursor

    def finish(self):
        if not self._finished or self._seen != self._expected:
            raise EvidenceError('incomplete_registry')
        return {'protocol': self._checks.profile, 'tools_verified': len(self._seen),
                'pages': self._pages, 'contract_sha256': self._checks.contract_sha256,
                'profile_qualified': False}
