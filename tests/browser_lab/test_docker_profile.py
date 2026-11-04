"""Daemon lifecycle using synthetic Docker replies and fresh owned test files."""
import copy
import json
from pathlib import Path
import shutil
import sys
from types import SimpleNamespace
import unittest
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/browser_lab'))
import controller
from contract import BrowserPlan, LABEL, SERVICES, VOLUMES
from docker_profile import DockerProfile
from worker_io import ROOT


class FakeIO:
    def __init__(self, plan, directory):
        self.plan, self.directory = plan, directory
        self.receipts, self.objects, self.calls = {}, {kind: {} for kind in ('container', 'volume', 'network')}, []
        self.images = {name: 'sha256:' + 'a' * 64 for name in SERVICES}
        self.image_os, self.health = 'linux', 'healthy'
        self.failed_stops, self.inspect_error = set(), None
        self.stop_error = RuntimeError('synthetic owned stop failure')
        self.fail_up, self.fail_sql = False, False

    def save(self, name, value):
        self.receipts[name] = copy.deepcopy(value)
        if name == 'compose.public.json':
            (self.directory / name).write_text(json.dumps(value), encoding='utf-8')

    def public(self, name, *, optional=False):
        if name not in self.receipts and not optional:
            raise ValueError('synthetic receipt missing')
        return copy.deepcopy(self.receipts.get(name))

    def command(self, label, argv, **options):
        self.calls.append((label, argv))
        def result(value, code=0, error=b''):
            return SimpleNamespace(returncode=code, stdout=json.dumps(value).encode(), stderr=error)
        if argv[1:3] == ['image', 'inspect']:
            return result({'id': argv[-1], 'os': self.image_os})
        if argv[1] in self.objects and argv[2] == 'inspect':
            kind, name = argv[1], argv[-1]
            if self.inspect_error:
                return result(None, 1, self.inspect_error)
            if name not in self.objects[kind]:
                return result(None, 1, ('Error response from daemon: No such ' + kind + ': ' + name).encode())
            return result(self.objects[kind][name])
        if argv[1] == 'compose':
            services = argv[argv.index('never') + 1:]
            for service in services:
                self.objects['container'][self.plan.container(service)] = {'id': format(SERVICES.index(service) + 1, '064x'),
                    'name': '/' + self.plan.container(service), 'image': self.images[service], 'labels': {LABEL: self.plan.run},
                    'running': True, 'status': 'running', 'health': self.health,
                    'networks': {self.plan.project + '-net': {}}}
            self.objects['network'][self.plan.project + '-net'] = {'name': self.plan.project + '-net',
                'internal': True, 'labels': {LABEL: self.plan.run}}
            for slot in VOLUMES:
                self.objects['volume'][self.plan.volume(slot)] = {'name': self.plan.volume(slot), 'labels': {LABEL: self.plan.run}}
            if self.fail_up:
                raise RuntimeError('synthetic incomplete daemon operation')
            return result({})
        if argv[1:3] == ['container', 'stop']:
            if argv[-1] in self.failed_stops:
                raise self.stop_error
            for row in self.objects['container'].values():
                if row['id'] == argv[-1]:
                    row.update(running=False, status='exited')
            return result({})
        if label == 'cleanup-owned-sql-fixtures':
            if self.fail_sql:
                raise RuntimeError('synthetic SQL helper failure')
            return result({'action': 'cleanup', 'fixture_complete': True})
        if argv[1:3] == ['container', 'exec']:
            return result({})
        raise AssertionError('Unexpected fake command')


class DockerProfileTests(unittest.TestCase):
    def setUp(self):
        self.plan = BrowserPlan(str(uuid.uuid4()))
        self.directory = ROOT / 'storage/mcp-google-docker' / self.plan.run
        self.directory.mkdir(parents=True)
        for name in ('application.private.env', 'mysql.private.env', 'compose-empty.public.env'):
            (self.directory / name).write_bytes(b'# synthetic test placeholder\n')
        self.io = FakeIO(self.plan, self.directory)
        self.p = DockerProfile(self.io, self.io.images)

    def tearDown(self):
        self.assertEqual(self.directory.resolve().parent, (ROOT / 'storage/mcp-google-docker').resolve())
        self.assertEqual(self.directory.name, self.plan.run)
        self.assertFalse(self.directory.is_symlink() or self.directory.is_junction())
        shutil.rmtree(self.directory)

    def prepared(self):
        self.p.acquire()
        self.p.up(['mysql', 'php'])
        self.p.up(['web'])

    def stops(self):
        return [args[-1] for label, args in self.io.calls if args[1:3] == ['container', 'stop']]

    def test_acquisition_precedes_creation_and_all_started_resources_are_attributed(self):
        self.prepared()
        self.assertEqual(self.io.receipts['claim.public.json'], self.p.claim_value())
        self.assertTrue(all(row['complete'] for row in self.io.receipts['mutation.public.json']['operations']))
        self.p.wait()
        self.assertEqual(self.p.stop_all()['stopped'], 3)
        self.assertEqual(len(set(self.stops())), 3)

    def test_preexisting_volume_or_non_linux_image_refuses_without_claim_or_creation(self):
        for mode in ('volume', 'image'):
            with self.subTest(mode=mode):
                self.io.image_os = 'windows' if mode == 'image' else 'linux'
                if mode == 'volume':
                    self.io.objects['volume'][self.plan.volume('app-storage')] = {'name': self.plan.volume('app-storage')}
                with self.assertRaises(ValueError):
                    self.p.acquire()
                self.assertNotIn('claim.public.json', self.io.receipts)
                self.assertFalse(any(args[1] == 'compose' for _, args in self.io.calls))

    def test_daemon_permission_or_foreign_error_is_never_treated_as_absence(self):
        for message in (b'permission denied', b'No such container: foreign'):
            self.io.inspect_error = message
            with self.assertRaisesRegex(ValueError, 'absence_unverifiable'):
                self.p.acquire()
        self.assertNotIn('claim.public.json', self.io.receipts)

    def test_first_stop_failure_does_not_abandon_other_owned_ids(self):
        self.prepared()
        web = self.p.owned('web')
        self.io.failed_stops = {web['id']}
        with self.assertRaises(RuntimeError) as raised:
            self.p.stop_all()
        self.assertIs(raised.exception, self.io.stop_error)
        self.assertEqual(len(set(self.stops())), 3)
        self.assertTrue(self.p.owned('web')['running'])
        self.assertFalse(self.p.owned('mysql')['running'])
        self.assertFalse(self.p.owned('php')['running'])

    def test_all_stop_failures_preserve_first_and_still_attempt_all(self):
        self.prepared()
        self.io.failed_stops = {self.p.owned(service)['id'] for service in SERVICES}
        with self.assertRaises(RuntimeError) as raised:
            self.p.stop_all()
        self.assertIs(raised.exception, self.io.stop_error)
        self.assertEqual(len(set(self.stops())), 3)

    def test_foreign_label_refuses_before_any_stop(self):
        self.prepared()
        self.io.objects['container'][self.plan.container('web')]['labels'] = {LABEL: str(uuid.uuid4())}
        with self.assertRaisesRegex(ValueError, 'label_mismatch'):
            self.p.stop_all()
        self.assertEqual(self.stops(), [])

    def test_foreign_image_refuses_before_any_stop(self):
        self.prepared()
        self.io.objects['container'][self.plan.container('web')]['image'] = 'sha256:' + 'b' * 64
        with self.assertRaisesRegex(ValueError, 'identity_mismatch'):
            self.p.stop_all()
        self.assertEqual(self.stops(), [])

    def test_extra_network_blocks_exec_but_owned_cleanup_still_stops_its_ids(self):
        self.prepared()
        self.io.objects['container'][self.plan.container('php')]['networks']['shared'] = {}
        before = len([a for _, a in self.io.calls if a[1:3] == ['container', 'exec']])
        with self.assertRaisesRegex(ValueError, 'network_mismatch'):
            self.p.exec_php('test-readonly', ['-r', 'exit(0);'])
        self.assertEqual(before, len([a for _, a in self.io.calls if a[1:3] == ['container', 'exec']]))
        self.assertEqual(self.p.stop_all()['stopped'], 3)

    def test_unknown_remote_start_is_not_clean_even_if_no_container_were_observed(self):
        self.p.acquire()
        self.io.save('mutation.public.json', {'run_id': self.plan.run,
            'operations': [{'operation': 'compose-up', 'services': ['mysql', 'php'], 'complete': False}]})
        with self.assertRaisesRegex(ValueError, 'remote_mutation_unresolved'):
            controller.cleanup(self.io, self.io.images)
        receipt = self.io.receipts['cleanup.public.json']
        self.assertFalse(receipt['daemon_cleanup_verified'])
        self.assertTrue(receipt['remote_mutation_uncertain'])
        self.assertEqual(receipt['stopped'], 0)

    def test_corrupt_mutation_journal_still_attempts_all_owned_stops(self):
        self.prepared()
        self.io.receipts['mutation.public.json'] = {'run_id': self.plan.run, 'operations': 'invalid'}
        with self.assertRaisesRegex(ValueError, 'mutation_journal_invalid'):
            controller.cleanup(self.io, self.io.images)
        self.assertEqual(len(set(self.stops())), 3)

    def test_sql_cleanup_failure_stops_web_then_all_remaining_services(self):
        self.prepared()
        self.io.receipts['fixture-state.public.json'] = {'run_id': self.plan.run, 'setup_started': True, 'setup_complete': True}
        self.io.fail_sql = True
        with self.assertRaisesRegex(RuntimeError, 'SQL helper failure'):
            controller.cleanup(self.io, self.io.images)
        self.assertEqual(len(set(self.stops())), 3)
        self.assertFalse(self.io.receipts['cleanup.public.json']['sql_cleanup_verified'])

    def test_missing_claim_grants_no_authority_to_inspect_or_stop(self):
        result = controller.cleanup(self.io, self.io.images)
        self.assertFalse(result['claimed'])
        self.assertEqual(self.io.calls, [])

    def test_foreign_claim_refuses_without_inspecting_objects(self):
        self.io.receipts['claim.public.json'] = {**self.p.claim_value(), 'run_id': str(uuid.uuid4())}
        with self.assertRaisesRegex(ValueError, 'cleanup_claim_invalid'):
            controller.cleanup(self.io, self.io.images)
        self.assertEqual(self.io.calls, [])

    def test_health_deadline_uses_bounded_virtual_wait(self):
        self.io.health = 'starting'
        self.p.acquire()
        self.p.up(['mysql', 'php'])
        elapsed = [0.0]
        def pause(value):
            elapsed[0] += value
        with self.assertRaisesRegex(ValueError, 'health_deadline'):
            self.p.wait(timeout=2, clock=lambda: elapsed[0], pause=pause)
        self.assertEqual(elapsed[0], 2)


if __name__ == '__main__':
    unittest.main()
