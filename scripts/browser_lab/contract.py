"""Closed identities for the synthetic Google/MCP Docker browser profile."""
from dataclasses import dataclass
from uuid import UUID
import re

LABEL = 'io.emailing.browser.run'
SERVICES = ('mysql', 'php', 'web')
VOLUMES = ('mysql-data', 'app-storage', 'app-fpm')


def require(condition, code):
    if not condition:
        raise ValueError(code)


def image_id(value):
    require(isinstance(value, str) and re.fullmatch('sha256:[0-9a-f]{64}', value) is not None,
            'browser_immutable_local_image_required')
    return value


@dataclass(frozen=True)
class BrowserPlan:
    run: str

    def __post_init__(self):
        try:
            valid = isinstance(self.run, str) and str(UUID(self.run)) == self.run and UUID(self.run).version == 4
        except (ValueError, TypeError, AttributeError):
            valid = False
        require(valid, 'browser_uuid4_required')

    @property
    def project(self):
        return 'emailing-b12-' + UUID(self.run).hex

    @property
    def labels(self):
        return {LABEL: self.run}

    def container(self, service):
        require(service in SERVICES, 'browser_service_refused')
        return self.project + '-' + service

    def volume(self, slot):
        require(slot in VOLUMES, 'browser_volume_refused')
        return self.project + '-' + slot

    def summary(self):
        return {'run_id': self.run, 'project': self.project, 'services': list(SERVICES),
            'new_volumes': [self.volume(slot) for slot in VOLUMES], 'network_internal': True,
            'http_origin': 'http://127.0.0.1:18967', 'deny_proxy': 'http://127.0.0.1:18968',
            'oauth_callback': 'http://127.0.0.1:18743/callback', 'http_runtime': 'Nginx + PHP-FPM',
            'tls_qualified': False, 'google_real_exercised': False, 'native_agent_clients_exercised': False,
            'browser_cases_expected': 9, 'automatic_volume_deletion': False, 'profile_accepted': False}
