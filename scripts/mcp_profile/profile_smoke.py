"""Read-only fixture smoke checks, distinct from restart/restore/client qualification."""
from dataclasses import dataclass, field
from uuid import UUID

from profile_receipts import EvidenceError
from profile_transport import decode_json_bytes, validated_origin


@dataclass(frozen=True, slots=True, repr=False)
class SmokeInputs:
    token: str = field(repr=False)
    account_id: int
    user_id: int
    grant_public_id: str = field(repr=False)
    contact_id: int
    denied_template_id: int

    @classmethod
    def from_bytes(cls, raw):
        try:
            if type(raw) is not bytes or len(raw) > 16384:
                raise EvidenceError('invalid_private_input')
            data = decode_json_bytes(raw)
            expected = {'schema_version', 'token', 'account_id', 'user_id', 'grant_public_id',
                        'contact_id', 'denied_template_id'}
            if (type(data) is not dict or set(data) != expected
                    or type(data['schema_version']) is not int or data['schema_version'] != 1):
                raise EvidenceError('invalid_private_input')
            for key in ['account_id', 'user_id', 'contact_id', 'denied_template_id']:
                if type(data[key]) is not int or not 1 <= data[key] <= 9007199254740991:
                    raise EvidenceError('invalid_private_input')
            token = data['token']
            if (type(token) is not str or not 1 <= len(token) <= 4096
                    or any(ord(char) < 33 or ord(char) >= 127 for char in token)):
                raise EvidenceError('invalid_private_input')
            grant = data['grant_public_id']
            if type(grant) is not str or str(UUID(grant)) != grant:
                raise EvidenceError('invalid_private_input')
            return cls(**{key: data[key] for key in expected - {'schema_version'}})
        except Exception:
            raise EvidenceError('invalid_private_input') from None

    def summary(self):
        return {'schema_version': 1, 'fixture_identity_configured': True,
                'credentials_recorded': False, 'profile_qualified': False}


class ProfileSmoke:
    def __init__(self, issuer, *, endpoints, session):
        validated_origin(issuer)
        self._issuer = issuer
        self._endpoints, self._session = endpoints, session

    def run(self, inputs):
        if type(inputs) is not SmokeInputs:
            raise EvidenceError('invalid_private_input')

        def own_connection(dto):
            data = dto['data']
            return (type(data['account']['id']) is int and data['account']['id'] == inputs.account_id
                    and type(data['principal']['user_id']) is int
                    and data['principal']['user_id'] == inputs.user_id
                    and data['connection']['public_id'] == inputs.grant_public_id
                    and data['connection']['effective_scopes'] == ['contacts.read']
                    and data['server']['issuer_uri'] == self._issuer
                    and data['server']['resource_uri'] == self._issuer + '/mcp')

        def own_contact(dto):
            value = dto['data']['contact']['id']
            return type(value) is int and value == inputs.contact_id

        try:
            self._endpoints.run()
            self._session.open()
            self._session.allowed('connection_info', {}, validate_fixture=own_connection)
            registry = self._session.registry(['contacts.read'])
            self._session.allowed('contacts_get', {'contact_id': inputs.contact_id}, validate_fixture=own_contact)
            self._session.denied('templates_get', {'template_id': inputs.denied_template_id})
        except Exception:
            raise EvidenceError('profile_smoke_failed') from None
        return {'kind': 'mcp-profile-read-only-smoke', 'readiness_verified': True,
                'metadata_verified': 3, 'identity_verified': True,
                'useful_read_verified': True, 'permission_denial_verified': True,
                'tools_verified': registry['tools_verified'],
                'profile_qualified': False, 'native_client_qualified': False,
                'restart_verified': False, 'restore_verified': False,
                'revocation_verified': False, 'google_verified': False}
