"""Secret-free HTTP observations for a future profile runner, never profile qualification."""
from dataclasses import dataclass
from datetime import datetime, timezone
from hashlib import sha256
import json
import os
from pathlib import Path
from uuid import UUID, uuid4

PROFILES = frozenset({'laragon', 'docker'})
OPERATIONS = frozenset({
    'readiness', 'authorization_metadata', 'resource_metadata',
    'initialize', 'initialized_notification', 'server_discover', 'tools_list',
    'allowed_call', 'denied_call', 'revoked_call', 'expired_call',
    'token_exchange', 'token_revocation',
})
METHODS = frozenset({'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'})
ERRORS = frozenset({
    'network_error', 'tls_error', 'timeout', 'redirect_rejected', 'body_limit',
    'invalid_json', 'http_error', 'protocol_error',
})


class EvidenceError(Exception):
    """Only fixed codes cross the boundary; OS exceptions can contain private paths."""


def _nonnegative_integer(value):
    return type(value) is int and value >= 0


@dataclass(frozen=True, slots=True)
class HttpReceipt:
    operation: str
    method: str
    status: int | None
    elapsed_ns: int
    response_bytes: int
    error: str | None = None

    def __post_init__(self):
        if type(self.operation) is not str or self.operation not in OPERATIONS:
            raise EvidenceError('invalid_observation')
        if type(self.method) is not str or self.method not in METHODS:
            raise EvidenceError('invalid_observation')
        if not _nonnegative_integer(self.elapsed_ns) or not _nonnegative_integer(self.response_bytes):
            raise EvidenceError('invalid_observation')
        if self.status is not None and (type(self.status) is not int or not 100 <= self.status <= 599):
            raise EvidenceError('invalid_observation')
        if self.error is not None and (type(self.error) is not str or self.error not in ERRORS):
            raise EvidenceError('invalid_observation')
        if self.status is None and self.error is None:
            raise EvidenceError('invalid_observation')


@dataclass(frozen=True, slots=True)
class ReceiptReference:
    relative_path: str
    sha256: str


class ReceiptStore:
    """Append-only, newly owned run directory below storage/app/private."""

    def __init__(self, project_root, profile, run_id):
        if type(profile) is not str or profile not in PROFILES:
            raise EvidenceError('invalid_profile')
        try:
            parsed = UUID(run_id) if type(run_id) is str else None
        except (ValueError, AttributeError):
            parsed = None
        if parsed is None or parsed.version != 4 or str(parsed) != run_id:
            raise EvidenceError('invalid_run_id')
        try:
            self._project = Path(project_root).resolve(strict=True)
            if not self._project.is_dir():
                raise EvidenceError('unsafe_output')
            prefix = self._project / 'storage/app/private/mcp-profile-evidence'
            self._assert_tree(prefix)
            prefix.mkdir(parents=True, exist_ok=True, mode=0o700)
            self._assert_tree(prefix)
            self._directory = prefix / run_id
            try:
                self._directory.mkdir(mode=0o700)
            except FileExistsError:
                raise EvidenceError('run_exists') from None
            self._assert_tree(self._directory)
        except OSError:
            raise EvidenceError('unsafe_output') from None
        self._profile = profile
        self._run_id = run_id

    def _assert_tree(self, path):
        try:
            relative = path.relative_to(self._project)
            current = self._project
            for segment in relative.parts:
                current = current / segment
                junction = getattr(current, 'is_junction', None)
                if current.is_symlink() or (junction is not None and junction()):
                    raise EvidenceError('unsafe_output')
                if current.exists() and not current.is_dir():
                    raise EvidenceError('unsafe_output')
            if path.exists() and path.resolve(strict=True) != path:
                raise EvidenceError('unsafe_output')
        except (ValueError, OSError):
            raise EvidenceError('unsafe_output') from None

    def write(self, receipt):
        if type(receipt) is not HttpReceipt:
            raise EvidenceError('invalid_observation')
        self._assert_tree(self._directory)
        if not self._directory.is_dir():
            raise EvidenceError('unsafe_output')
        # Construct each field explicitly. No arbitrary diagnostic or response data.
        data = {
            'schema_version': 1, 'kind': 'mcp-profile-http-observation',
            'profile': self._profile, 'run_id': self._run_id,
            'observed_at_utc': datetime.now(timezone.utc).isoformat(),
            'operation': receipt.operation, 'method': receipt.method,
            'status': receipt.status, 'elapsed_ns': receipt.elapsed_ns,
            'response_bytes': receipt.response_bytes, 'error': receipt.error,
            'credentials_recorded': False, 'request_body_recorded': False,
            'response_body_recorded': False, 'headers_recorded': False,
            # HTTP200 alone proves neither an allowed tool call nor a client session.
            'qualifies_profile': False,
        }
        raw = (json.dumps(data, ensure_ascii=True, sort_keys=True, indent=2) + '\n').encode('utf-8')
        target = self._directory / ('http-' + str(uuid4()) + '.json')
        flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | getattr(os, 'O_NOFOLLOW', 0)
        try:
            handle = os.open(target, flags, 0o600)
            with os.fdopen(handle, 'wb') as stream:
                if stream.write(raw) != len(raw):
                    raise OSError
                stream.flush()
                os.fsync(stream.fileno())
        except FileExistsError:
            raise EvidenceError('receipt_exists') from None
        except OSError:
            # A partial private file is preserved; no reference blesses it as evidence.
            raise EvidenceError('write_failed') from None
        return ReceiptReference(target.relative_to(self._project).as_posix(), sha256(raw).hexdigest())
