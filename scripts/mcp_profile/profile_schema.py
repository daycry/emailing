"""Validate successful MCP DTOs using the application's pinned PHP schemas."""
import json
from pathlib import Path
import shutil
import subprocess

from profile_protocol import ProtocolChecks
from profile_receipts import EvidenceError
from profile_transport import decode_json_bytes

MAX_INPUT = 8388608


class PhpSchemaValidator:
    def __init__(self, project_root):
        self._root = Path(project_root).resolve()
        self._bridge = Path(__file__).with_name('profile_schema_bridge.php').resolve()
        if self._root != self._bridge.parents[2]:
            raise EvidenceError('invalid_schema_root')
        self.php_binary = shutil.which('php')
        if not self.php_binary or not self._bridge.is_file():
            raise EvidenceError('schema_unavailable')
        try:
            contract = decode_json_bytes((self._root / 'docs/api/mcp-tools.json').read_bytes())
            self._names = frozenset(item['name'] for item in contract['operations'])
        except Exception:
            raise EvidenceError('schema_unavailable') from None

    def __call__(self, name, value):
        if type(name) is not str or name not in self._names:
            raise EvidenceError('invalid_operation')
        try:
            payload = json.dumps({'operation': name, 'value': value}, ensure_ascii=False,
                                 allow_nan=False).encode('utf-8')
        except (ValueError, TypeError, UnicodeError, RecursionError):
            raise EvidenceError('invalid_schema_value') from None
        if len(payload) > MAX_INPUT:
            raise EvidenceError('invalid_schema_value')
        try:
            result = subprocess.run([self.php_binary, '-d', 'zend.exception_ignore_args=1',
                                     str(self._bridge)], cwd=self._root, input=payload,
                                    capture_output=True, timeout=30)
            if result.returncode != 0 or result.stderr or len(result.stdout) > 100:
                raise EvidenceError('schema_unavailable')
            reply = decode_json_bytes(result.stdout)
            if (type(reply) is not dict or set(reply) != {'available', 'valid'}
                    or reply['available'] is not True or type(reply['valid']) is not bool):
                raise EvidenceError('schema_unavailable')
        except Exception:
            raise EvidenceError('schema_unavailable') from None
        return reply['valid']


class SchemaBoundChecks(ProtocolChecks):
    def __init__(self, profile, issuer, contract_bytes, *, schema_validator):
        super().__init__(profile, issuer, contract_bytes)
        if not callable(schema_validator):
            raise EvidenceError('missing_schema_validator')
        self._schema_validator = schema_validator

    def allowed(self, exchange, expected_id, name, *, validate_fixture):
        if not callable(validate_fixture):
            raise EvidenceError('missing_fixture_validator')
        schema_error = None

        def validated_fixture(dto):
            nonlocal schema_error
            try:
                valid = self._schema_validator(name, dto)
            except Exception:
                schema_error = 'schema_unavailable'
                return False
            if valid is not True:
                schema_error = 'schema_mismatch'
                return False
            return validate_fixture(dto)

        try:
            return super().allowed(exchange, expected_id, name,
                                   validate_fixture=validated_fixture)
        except EvidenceError:
            if schema_error is not None:
                raise EvidenceError(schema_error) from None
            raise
