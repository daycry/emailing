"""Acquire and settle only the three names in one fresh browser run."""
import json
import re
import time

from compose import compose
from contract import LABEL, SERVICES, VOLUMES, image_id, require


class DockerProfile:
    def __init__(self, io, images):
        require(isinstance(images, dict) and set(images) == set(SERVICES), 'browser_three_images_required')
        self.images = {name: image_id(value) for name, value in images.items()}
        self.io, self.plan = io, io.plan
        self.claimed = False
        self.mutations = []

    def inspect(self, kind, name):
        require(kind in ('container', 'volume', 'network'), 'browser_inspect_kind_refused')
        fields = {'container': '{"id":{{json .Id}},"name":{{json .Name}},"image":{{json .Image}},"running":{{json .State.Running}},"status":{{json .State.Status}},"health":{{if .State.Health}}{{json .State.Health.Status}}{{else}}null{{end}},"labels":{{json .Config.Labels}},"networks":{{json .NetworkSettings.Networks}}}',
            'volume': '{"name":{{json .Name}},"labels":{{json .Labels}}}',
            'network': '{"name":{{json .Name}},"labels":{{json .Labels}},"internal":{{json .Internal}}}'}
        result = self.io.command('inspect-owned-' + kind, ['docker', kind, 'inspect', '--format', fields[kind], name], check=False)
        if result.returncode != 0:
            value = result.stderr.strip()
            fixed = rb'(?:Error response from daemon: |Error: )?No such ' + kind.encode() + rb': ' + re.escape(name.encode())
            alternate = (rb'(?:Error response from daemon: )?get ' + re.escape(name.encode()) + rb': no such volume'
                         if kind == 'volume' else rb'(?:Error response from daemon: )?network ' + re.escape(name.encode()) + rb' not found')
            require(re.fullmatch(fixed, value) is not None or kind != 'container' and re.fullmatch(alternate, value) is not None,
                    'browser_absence_unverifiable')
            return None
        require(len(result.stdout) <= 16384, 'browser_inspect_output_limit')
        value = json.loads(result.stdout)
        require(isinstance(value, dict), 'browser_inspection_invalid')
        return value

    def owned(self, service, *, optional=False):
        name = self.plan.container(service)
        value = self.inspect('container', name)
        if value is None and optional:
            return None
        require(isinstance(value, dict) and value.get('name') == '/' + name and value.get('image') == self.images[service]
                and isinstance(value.get('id'), str) and re.fullmatch('[0-9a-f]{64}', value['id']) is not None
                and type(value.get('running')) is bool, 'browser_container_identity_mismatch')
        self.labels(value)
        return value

    def labels(self, value):
        require(isinstance(value.get('labels'), dict) and value['labels'].get(LABEL) == self.plan.run,
                'browser_resource_label_mismatch')

    def isolated(self, row):
        require(isinstance(row.get('networks'), dict) and set(row['networks']) == {self.plan.project + '-net'},
                'browser_runtime_network_mismatch')

    def claim_value(self):
        return {'run_id': self.plan.run, 'images': self.images, 'absence_checked': True,
                'containers': [self.plan.container(service) for service in SERVICES],
                'volumes': [self.plan.volume(slot) for slot in VOLUMES], 'network': self.plan.project + '-net'}

    def acquire(self):
        require(not self.claimed, 'browser_already_acquired')
        for image in dict.fromkeys(self.images.values()):
            raw = self.io.command('inspect-local-image', ['docker', 'image', 'inspect', '--format',
                '{"id":{{json .Id}},"os":{{json .Os}}}', image]).stdout
            require(json.loads(raw) == {'id': image, 'os': 'linux'}, 'browser_local_linux_image_required')
        for service in SERVICES:
            require(self.inspect('container', self.plan.container(service)) is None, 'browser_container_preexists')
        for slot in VOLUMES:
            require(self.inspect('volume', self.plan.volume(slot)) is None, 'browser_volume_preexists')
        require(self.inspect('network', self.plan.project + '-net') is None, 'browser_network_preexists')
        self.io.save('claim.public.json', self.claim_value())  # Durable before any daemon mutation.
        self.claimed = True

    def recover_claim(self):
        value = self.io.public('claim.public.json', optional=True)
        if value is None:
            return False
        require(value == self.claim_value(), 'browser_cleanup_claim_invalid')
        self.claimed = True
        return True

    def up(self, services):
        require(self.claimed and services in (['mysql', 'php'], ['web']), 'browser_start_refused')
        self.mutations.append({'operation': 'compose-up', 'services': services, 'complete': False})
        self.io.save('mutation.public.json', {'run_id': self.plan.run, 'operations': self.mutations})
        path = self.io.directory / 'compose.public.json'
        if not path.exists():
            self.io.save('compose.public.json', compose(self.plan, self.images, self.io.directory))
        for name in ('application.private.env', 'mysql.private.env', 'compose-empty.public.env', 'compose.public.json'):
            p = self.io.directory / name
            require(p.is_file() and not p.is_symlink() and p.stat().st_nlink == 1, 'browser_configuration_refused')
        self.io.command('start-owned-services', ['docker', 'compose', '--project-directory', str(self.io.directory),
            '--env-file', str(self.io.directory / 'compose-empty.public.env'), '-p', self.plan.project,
            '-f', str(path), 'up', '-d', '--no-build', '--pull', 'never', *services], timeout=300)
        for service in services:
            observed = self.owned(service)
            require(observed['running'] is True, 'browser_service_not_running')
            self.isolated(observed)
        network = self.inspect('network', self.plan.project + '-net')
        require(network is not None and network.get('name') == self.plan.project + '-net'
                and network.get('internal') is True, 'browser_network_not_internal')
        self.labels(network)
        for slot in VOLUMES:
            volume = self.inspect('volume', self.plan.volume(slot))
            require(volume is not None and volume.get('name') == self.plan.volume(slot), 'browser_volume_identity_mismatch')
            self.labels(volume)
        self.mutations[-1]['complete'] = True
        self.io.save('mutation.public.json', {'run_id': self.plan.run, 'operations': self.mutations})

    def wait(self, *, timeout=240, clock=time.monotonic, pause=time.sleep):
        require(type(timeout) in (int, float) and 0 < timeout <= 600, 'browser_health_budget_refused')
        deadline = clock() + timeout
        while True:
            rows = [self.owned(service) for service in ('mysql', 'php')]
            require(all(row['running'] is True for row in rows), 'browser_dependency_stopped')
            require(all(row.get('health') in ('starting', 'healthy', 'unhealthy') for row in rows), 'browser_health_unverifiable')
            require(clock() < deadline, 'browser_health_deadline')
            if all(row['health'] == 'healthy' for row in rows):
                return
            pause(min(1, max(0, deadline - clock())))

    def exec_service(self, service, label, arguments, *, timeout=30, limit=1048576):
        row = self.owned(service)
        require(row['running'] is True, 'browser_exec_service_stopped')
        self.isolated(row)
        args = ['docker', 'container', 'exec']
        if service == 'php':
            args += ['--user', '33:33', '--workdir', '/var/www/emailing']
        return self.io.command(label, [*args, row['id'], *arguments], timeout=timeout, limit=limit)

    def exec_php(self, label, arguments, *, timeout=30, limit=1048576):
        return self.exec_service('php', label, ['php', '-d', 'zend.exception_ignore_args=1', *arguments], timeout=timeout, limit=limit)

    def environment(self):
        row = self.owned('php')
        require(row['running'] is True, 'browser_php_not_running')
        self.isolated(row)
        # Host helper receives attribution only, never generated keys/passwords.
        import os
        return {**os.environ, 'BROWSER_CONTROLLER_GATED': '1', 'GOOGLE_BROWSER_TEST': '1',
            'GOOGLE_BROWSER_PROFILE': 'docker', 'GOOGLE_BROWSER_RUN': self.plan.run,
            'GOOGLE_BROWSER_CONTAINER_ID': row['id'], 'GOOGLE_BROWSER_PHP_IMAGE': self.images['php']}

    def stop_all(self):
        require(self.claimed, 'browser_cleanup_without_claim')
        # Verify all identities before any stop; one command failure must not
        # abandon the other owned IDs (the recovery B-02 regression).
        rows = [self.owned(service, optional=True) for service in SERVICES]
        rows = [row for row in rows if row is not None]
        primary = None
        for row in reversed(rows):
            if row['running']:
                try:
                    self.io.command('stop-owned-container', ['docker', 'container', 'stop', '--time', '90', row['id']], timeout=110)
                except BaseException as error:
                    if primary is None:
                        primary = error
                    else:
                        primary.add_note('browser_additional_stop_failed')
        try:
            after = [self.owned(service, optional=True) for service in SERVICES]
            after = [row for row in after if row is not None]
            require([row['id'] for row in after] == [row['id'] for row in rows]
                    and all(row['running'] is False for row in after), 'browser_cleanup_state_unverified')
        except BaseException as error:
            if primary is None:
                primary = error
            else:
                primary.add_note('browser_stop_verification_failed')
        if primary is not None:
            raise primary
        return {'stopped': len(rows), 'volumes_retained': True, 'network_retained': True}
