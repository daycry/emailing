"""Bound OAuth code exchange for the profile probe, not browser/native-client qualification."""
from dataclasses import dataclass, field
import re
from urllib.parse import urlencode, urlsplit
from uuid import UUID

from profile_endpoints import SCOPES
from profile_protocol import _header
from profile_receipts import EvidenceError, HttpReceipt
from profile_transport import HttpExchange, validated_origin

TOKEN = re.compile(r'[1-9][0-9]{0,19}\|[A-Za-z0-9]{64}')
CODE = re.compile(r'[A-Za-z0-9._~-]{32,512}')
VERIFIER = re.compile(r'[A-Za-z0-9._~-]{43,128}')


@dataclass(frozen=True, slots=True, repr=False)
class OAuthCredential:
    bearer: str = field(repr=False)
    origin: str = field(repr=False)
    client_id: str = field(repr=False)
    expires_in: int
    scopes: tuple

    def summary(self):
        return {'access_token_received': True, 'expires_in': self.expires_in,
                'scope_count': len(self.scopes), 'refresh_token_received': False,
                'profile_qualified': False, 'native_client_qualified': False}


class OAuthExchange:
    def __init__(self, origin, *, client_id, scopes, request, record):
        validated_origin(origin)
        try:
            if type(client_id) is not str or str(UUID(client_id)) != client_id:
                raise EvidenceError('invalid_oauth_config')
            if (type(scopes) not in (list, tuple) or any(type(scope) is not str or scope not in SCOPES for scope in scopes)
                    or len(set(scopes)) != len(scopes) or not callable(request) or not callable(record)):
                raise EvidenceError('invalid_oauth_config')
        except (ValueError, AttributeError):
            raise EvidenceError('invalid_oauth_config') from None
        self._origin, self._client_id = origin, client_id
        self._scopes = tuple(scopes)
        self._request, self._record = request, record
        self._state = 'new'

    def _post(self, operation, path, fields):
        try:
            body = urlencode(fields).encode('ascii')
            exchange = self._request(operation, 'POST', self._origin + path,
                                     headers={'Content-Type': 'application/x-www-form-urlencoded'}, body=body)
            if (type(exchange) is not HttpExchange or type(exchange.receipt) is not HttpReceipt
                    or exchange.receipt.operation != operation or exchange.receipt.method != 'POST'):
                raise EvidenceError('invalid_oauth_observation')
            self._record(exchange.receipt)
            if (exchange.receipt.status != 200 or exchange.receipt.error is not None
                    or type(exchange.body) is not bytes or len(exchange.body) > 8192
                    or 'no-store' not in {part.strip().lower() for part in _header(exchange, 'Cache-Control').split(',')}):
                raise EvidenceError('oauth_exchange_failed')
            return exchange
        except Exception:
            raise EvidenceError('oauth_exchange_failed') from None

    def exchange(self, *, code, verifier, redirect_uri):
        if self._state != 'new':
            raise EvidenceError('oauth_exchange_already_attempted')
        try:
            if type(code) is not str or not CODE.fullmatch(code):
                raise EvidenceError('invalid_oauth_input')
            if type(verifier) is not str or not VERIFIER.fullmatch(verifier):
                raise EvidenceError('invalid_oauth_input')
            if (type(redirect_uri) is not str or len(redirect_uri) > 2048
                    or any(ord(char) < 33 or ord(char) > 126 or char == '\\' for char in redirect_uri)):
                raise EvidenceError('invalid_oauth_input')
            target = urlsplit(redirect_uri)
            if (target.scheme != 'http' or target.hostname not in ['127.0.0.1', 'localhost']
                    or target.username is not None or target.password is not None
                    or target.port is None or not 1 <= target.port <= 65535
                    or target.path != '/callback' or target.query or target.fragment):
                raise EvidenceError('invalid_oauth_input')
        except (ValueError, UnicodeError):
            raise EvidenceError('invalid_oauth_input') from None
        self._state = 'attempted'
        try:
            exchange = self._post('token_exchange', '/oauth/token', {
                'grant_type': 'authorization_code', 'client_id': self._client_id,
                'code': code, 'code_verifier': verifier, 'redirect_uri': redirect_uri,
                'resource': self._origin + '/mcp'})
            if _header(exchange, 'Content-Type').split(';', 1)[0].strip().lower() != 'application/json':
                raise EvidenceError('invalid_token_response')
            data = exchange.json_body()
            if (type(data) is not dict or set(data) != {'access_token', 'token_type', 'expires_in', 'scope'}
                    or type(data['access_token']) is not str or not TOKEN.fullmatch(data['access_token'])
                    or data['token_type'] != 'Bearer' or type(data['expires_in']) is not int
                    or not 1 <= data['expires_in'] <= 3600 or type(data['scope']) is not str):
                raise EvidenceError('invalid_token_response')
            scopes = data['scope'].split(' ') if data['scope'] else []
            if len(set(scopes)) != len(scopes) or set(scopes) != set(self._scopes):
                raise EvidenceError('invalid_token_response')
            credential = OAuthCredential(data['access_token'], self._origin, self._client_id,
                                         data['expires_in'], tuple(scopes))
            self._state = 'authorized'
            return credential
        except Exception:
            self._state = 'failed'
            raise EvidenceError('oauth_exchange_failed') from None

    def revoke(self, credential):
        if (type(credential) is not OAuthCredential or credential.origin != self._origin
                or credential.client_id != self._client_id):
            raise EvidenceError('credential_binding_mismatch')
        if self._state != 'authorized':
            raise EvidenceError('oauth_revocation_unavailable')
        self._state = 'revocation_attempted'
        try:
            exchange = self._post('token_revocation', '/oauth/revoke', {
                'client_id': self._client_id, 'token': credential.bearer})
            if exchange.body != b'':
                raise EvidenceError('invalid_revocation_acknowledgement')
            self._state = 'revoked'
            return {'revocation_acknowledged': True, 'subsequent_http401_verified': False,
                    'profile_qualified': False, 'native_client_qualified': False}
        except Exception:
            self._state = 'failed'
            raise EvidenceError('oauth_revocation_failed') from None
