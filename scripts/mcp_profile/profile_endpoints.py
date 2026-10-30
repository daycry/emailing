"""Check this application's exact OAuth metadata before sending any credentials."""
from profile_protocol import _header, _same
from profile_receipts import EvidenceError, HttpReceipt
from profile_transport import HttpExchange, validated_origin

SCOPES = frozenset({'activity.read', 'campaigns.read', 'campaigns.write', 'contacts.read',
                    'contacts.write', 'mail.send', 'suppressions.read', 'suppressions.write',
                    'templates.read', 'templates.write'})


class EndpointChecks:
    def __init__(self, issuer, *, request, record):
        validated_origin(issuer)
        if not callable(request) or not callable(record):
            raise EvidenceError('invalid_endpoint_config')
        self._issuer, self._request, self._record = issuer, request, record
        self._started = False

    def _get(self, operation, path, *, metadata=True):
        try:
            exchange = self._request(operation, 'GET', self._issuer + path)
            if (type(exchange) is not HttpExchange or type(exchange.receipt) is not HttpReceipt
                    or exchange.receipt.operation != operation or exchange.receipt.method != 'GET'):
                raise EvidenceError('invalid_transport_observation')
            self._record(exchange.receipt)
            if exchange.receipt.status != 200 or exchange.receipt.error is not None:
                raise EvidenceError('endpoint_unavailable')
            if not metadata:
                return None
            content_type = _header(exchange, 'Content-Type').split(';', 1)[0].strip().lower()
            cache = _header(exchange, 'Cache-Control')
            if (content_type != 'application/json'
                    or 'no-store' not in {part.strip().lower() for part in cache.split(',')}
                    or type(exchange.body) is not bytes or len(exchange.body) > 8388608):
                raise EvidenceError('invalid_metadata')
            document = exchange.json_body()
            if type(document) is not dict:
                raise EvidenceError('invalid_metadata')
            return document
        except Exception:
            raise EvidenceError('endpoint_check_failed') from None

    def _server(self, document):
        expected = {
            'issuer': self._issuer,
            'authorization_endpoint': self._issuer + '/oauth/authorize',
            'token_endpoint': self._issuer + '/oauth/token',
            'revocation_endpoint': self._issuer + '/oauth/revoke',
            'response_types_supported': ['code'],
            'grant_types_supported': ['authorization_code'],
            'code_challenge_methods_supported': ['S256'],
            'token_endpoint_auth_methods_supported': ['none'],
            'revocation_endpoint_auth_methods_supported': ['none'],
            'authorization_response_iss_parameter_supported': True,
            'client_id_metadata_document_supported': False,
        }
        if 'registration_endpoint' in document:
            expected['registration_endpoint'] = self._issuer + '/oauth/register'
        if set(document) != set(expected) | {'scopes_supported'}:
            raise EvidenceError('authorization_metadata_mismatch')
        if any(not _same(document[key], value) for key, value in expected.items()):
            raise EvidenceError('authorization_metadata_mismatch')
        scopes = document['scopes_supported']
        if (type(scopes) is not list or any(type(value) is not str for value in scopes)
                or len(scopes) != len(SCOPES) or set(scopes) != SCOPES):
            raise EvidenceError('authorization_metadata_mismatch')

    def _resource(self, document):
        expected = {'resource': self._issuer + '/mcp', 'authorization_servers': [self._issuer],
                    'scopes_supported': [], 'bearer_methods_supported': ['header'],
                    'resource_name': 'Emailing'}
        if not _same(document, expected):
            raise EvidenceError('resource_metadata_mismatch')

    def run(self):
        if self._started:
            raise EvidenceError('endpoint_check_already_started')
        self._started = True
        self._get('readiness', '/up', metadata=False)
        server = self._get('authorization_metadata', '/.well-known/oauth-authorization-server')
        self._server(server)
        for path in ['/.well-known/oauth-protected-resource', '/.well-known/oauth-protected-resource/mcp']:
            self._resource(self._get('resource_metadata', path))
        return {'readiness_verified': True, 'metadata_verified': 3,
                'dynamic_registration_advertised': 'registration_endpoint' in server,
                'profile_qualified': False, 'native_client_qualified': False}
