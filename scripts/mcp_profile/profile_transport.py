"""Bound diagnostic HTTP transport. It does not qualify deployments or native clients."""
from dataclasses import dataclass, field
import http.client
import json
import math
import os
import ssl
import time
from urllib.parse import urlsplit
import re

from profile_receipts import EvidenceError, HttpReceipt

ROUTES = {
    'readiness': ({'GET', 'HEAD'}, {'/up'}),
    'authorization_metadata': ({'GET'}, {'/.well-known/oauth-authorization-server'}),
    'resource_metadata': ({'GET'}, {
        '/.well-known/oauth-protected-resource',
        '/.well-known/oauth-protected-resource/mcp',
    }),
    **{operation: ({'POST'}, {'/mcp'}) for operation in [
        'initialize', 'initialized_notification', 'server_discover', 'tools_list',
        'allowed_call', 'denied_call', 'revoked_call', 'expired_call',
    ]},
    'token_exchange': ({'POST'}, {'/oauth/token'}),
    'token_revocation': ({'POST'}, {'/oauth/revoke'}),
}
FORBIDDEN_HEADERS = frozenset({
    'host', 'content-length', 'transfer-encoding', 'proxy-authorization',
    'proxy-connection', 'connection', 'trailer', 'te', 'upgrade', 'accept-encoding',
})
REDIRECTS = frozenset({301, 302, 303, 307, 308})
HEADER_NAME = re.compile(r"^[!#$%&'*+.^_" + chr(96) + r"|~0-9A-Za-z-]+$")


def _uri(value):
    if type(value) is not str or not value.startswith('https://'):
        raise EvidenceError('invalid_url')
    if any(ord(char) <= 32 or ord(char) >= 127 or char == '\\' for char in value):
        raise EvidenceError('invalid_url')
    try:
        parts = urlsplit(value)
        port = parts.port
        if not parts.hostname or parts.username is not None or parts.password is not None:
            raise EvidenceError('invalid_url')
        if '%' in parts.netloc or (port is not None and not 1 <= port <= 65535):
            raise EvidenceError('invalid_url')
        return parts
    except ValueError:
        raise EvidenceError('invalid_url') from None


def _unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise EvidenceError('invalid_json')
        result[key] = value
    return result


def _invalid_constant(_value):
    raise EvidenceError('invalid_json')


def _finite_float(value):
    number = float(value)
    if not math.isfinite(number):
        raise EvidenceError('invalid_json')
    return number


def decode_json_bytes(raw):
    if type(raw) is not bytes:
        raise EvidenceError('invalid_json')
    try:
        return json.loads(raw.decode('utf-8'), object_pairs_hook=_unique_object,
                          parse_constant=_invalid_constant, parse_float=_finite_float)
    except (UnicodeError, ValueError, RecursionError):
        raise EvidenceError('invalid_json') from None


def validated_origin(origin):
    parts = _uri(origin)
    if origin != 'https://' + parts.netloc:
        raise EvidenceError('invalid_origin')
    return parts


@dataclass(frozen=True, slots=True)
class HttpExchange:
    receipt: HttpReceipt
    headers: tuple = field(repr=False)
    body: bytes = field(repr=False)

    def json_body(self):
        return decode_json_bytes(self.body)


class BoundHttpClient:
    def __init__(self, origin, *, timeout=30, max_response_bytes=64 * 1024 * 1024, ca_file=None):
        parts = validated_origin(origin)
        if type(timeout) not in (int, float) or not math.isfinite(timeout) or not 0 < timeout <= 300:
            raise EvidenceError('invalid_transport_config')
        if type(max_response_bytes) is not int or not 0 < max_response_bytes <= 128 * 1024 * 1024:
            raise EvidenceError('invalid_transport_config')
        if os.environ.get('SSLKEYLOGFILE'):
            raise EvidenceError('unsafe_tls_key_logging')
        try:
            context = ssl.create_default_context(cafile=ca_file)
            if context.verify_mode != ssl.CERT_REQUIRED or not context.check_hostname:
                raise EvidenceError('invalid_tls')
            context.minimum_version = ssl.TLSVersion.TLSv1_2
        except (OSError, ValueError):
            raise EvidenceError('invalid_tls') from None
        self._origin = origin
        self._parts = parts
        self._context = context
        self._timeout = timeout
        self._max_bytes = max_response_bytes

    def _request_input(self, operation, method, url, headers, body):
        route = ROUTES.get(operation) if type(operation) is str else None
        if route is None or type(method) is not str or method not in route[0]:
            raise EvidenceError('invalid_request')
        parts = _uri(url)
        if 'https://' + parts.netloc != self._origin:
            raise EvidenceError('origin_mismatch')
        if parts.path not in route[1] or url != self._origin + parts.path:
            raise EvidenceError('invalid_target')
        if body is not None and type(body) is not bytes:
            raise EvidenceError('invalid_request')
        if headers is not None and type(headers) is not dict:
            raise EvidenceError('invalid_request')
        clean = {
            'Host': self._parts.netloc, 'Connection': 'close', 'Accept-Encoding': 'identity',
            'Accept': 'application/json, text/event-stream',
        }
        seen = set()
        for key, value in (headers or {}).items():
            if type(key) is not str or not HEADER_NAME.fullmatch(key):
                raise EvidenceError('invalid_header')
            name = key.lower()
            if name in FORBIDDEN_HEADERS or name in seen:
                raise EvidenceError('invalid_header')
            if type(value) is not str or any(ord(char) < 32 or ord(char) >= 127 for char in value):
                raise EvidenceError('invalid_header')
            seen.add(name)
            for existing in list(clean):
                if existing.lower() == name:
                    del clean[existing]
            clean[key] = value
        return parts.path, clean

    def request(self, operation, method, url, *, headers=None, body=None):
        path, clean = self._request_input(operation, method, url, headers, body)
        started = time.monotonic_ns()
        connection = response = None
        status = None
        reply_headers = ()
        raw = b''
        returned_bytes = 0
        error = None
        try:
            # Direct connection: no ambient proxy discovery and no redirect machinery.
            connection = http.client.HTTPSConnection(self._parts.hostname, port=self._parts.port or 443,
                                                     timeout=self._timeout, context=self._context)
            connection.request(method, path, body=body, headers=clean)
            response = connection.getresponse()
            if type(response.status) is not int or not 100 <= response.status <= 599:
                raise http.client.HTTPException
            status = response.status
            reply_headers = tuple(response.getheaders())
            if status in REDIRECTS:
                error = 'redirect_rejected'
            else:
                raw = response.read(self._max_bytes + 1)
                if type(raw) is not bytes:
                    raise http.client.HTTPException
                returned_bytes = len(raw)
                if returned_bytes > self._max_bytes:
                    error, raw = 'body_limit', b''
                elif status >= 300:
                    error = 'http_error'
        except ssl.SSLError:
            error, raw = 'tls_error', b''
        except TimeoutError:
            error, raw = 'timeout', b''
        except http.client.HTTPException:
            error, raw = 'protocol_error', b''
        except OSError:
            error, raw = 'network_error', b''
        finally:
            for resource in (response, connection):
                if resource is not None:
                    try:
                        resource.close()
                    except (OSError, http.client.HTTPException):
                        error = error or 'network_error'
        receipt = HttpReceipt(operation=operation, method=method, status=status,
                              elapsed_ns=max(0, time.monotonic_ns() - started),
                              response_bytes=returned_bytes, error=error)
        return HttpExchange(receipt, reply_headers, raw)
