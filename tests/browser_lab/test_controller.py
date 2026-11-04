"""Orchestration, report and cleanup cuts without SQL, Docker or a browser."""
import contextlib
import copy
import io
import json
from pathlib import Path
import shutil
import sys
from types import SimpleNamespace
import unittest
from unittest.mock import patch
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'scripts/browser_lab'))
import controller
from contract import BrowserPlan, SERVICES
from results import titles, verify_report
from worker_io import ROOT, WorkerIO


class ReportTests(unittest.TestCase):
    def report(self):
        expected = titles('full')
        return {'errors': [], 'stats': {'expected': 9, 'unexpected': 0, 'flaky': 0, 'skipped': 0},
            'config': {'workers': 1, 'projects': [{'retries': 0, 'repeatEach': 1, 'timeout': 60000}]},
            'suites': [{'specs': [{'file': 'mcp-google.spec.mjs', 'id': str(index), 'title': name, 'ok': True,
                'tests': [{'status': 'expected', 'expectedStatus': 'passed', 'timeout': 60000,
                           'results': [{'status': 'passed', 'retry': 0, 'errors': []}]}]}
                for index, name in enumerate(expected)]}]}

    def test_complete_report_has_exact_original_cases(self):
        result = verify_report(json.dumps(self.report()).encode(), titles('full'))
        self.assertEqual(result, {'cases': 9, 'passed': 9, 'failed': 0, 'flaky': 0, 'skipped': 0, 'retries': 0})

    def test_missing_duplicate_foreign_or_retried_case_refuses(self):
        for mode in ('missing', 'duplicate', 'foreign', 'retry', 'expected-failure', 'flaky', 'global-error', 'relaxed-timeout'):
            with self.subTest(mode=mode):
                value = self.report()
                specs = value['suites'][0]['specs']
                if mode == 'missing':
                    specs.pop()
                elif mode == 'duplicate':
                    specs[-1] = copy.deepcopy(specs[0])
                elif mode == 'foreign':
                    specs[-1]['file'] = 'other.spec.mjs'
                elif mode == 'retry':
                    specs[0]['tests'][0]['results'].append(copy.deepcopy(specs[0]['tests'][0]['results'][0]))
                elif mode == 'expected-failure':
                    specs[0]['tests'][0]['expectedStatus'] = 'failed'
                elif mode == 'flaky':
                    value['stats']['flaky'] = 1
                elif mode == 'global-error':
                    value['errors'] = [{'message': 'synthetic'}]
                else:
                    value['config']['projects'][0]['timeout'] = 120000
                with self.assertRaises(ValueError):
                    verify_report(json.dumps(value).encode(), titles('full'))

    def test_stability_selection_is_separate_and_never_accepts_full_report(self):
        selected = titles('e2e08')
        self.assertEqual(len(selected), 1)
        with self.assertRaises(ValueError):
            verify_report(json.dumps(self.report()).encode(), selected)
        value = self.report()
        value['stats']['expected'] = 1
        value['suites'][0]['specs'] = [spec for spec in value['suites'][0]['specs'] if spec['title'] in selected]
        self.assertEqual(verify_report(json.dumps(value).encode(), selected)['cases'], 1)


class ControllerTests(unittest.TestCase):
    def setUp(self):
        self.plan = BrowserPlan(str(uuid.uuid4()))
        self.images = {name: 'sha256:' + 'a' * 64 for name in SERVICES}
        self.directory = ROOT / 'storage/mcp-google-docker' / self.plan.run
        self.manifest = {'public-source': 'a' * 64}
        self.calls = []
        self.failure = None
        self.cleanup_failure = False
        self.change = lambda result: result
        self.patches = [patch.object(controller.qualification, 'sources', return_value=self.manifest),
            patch.object(controller.qualification, 'prerequisite', return_value={'synthetic': True}),
            patch.object(controller.qualification, 'app_sources', return_value={})]
        for item in self.patches:
            item.start()
            self.addCleanup(item.stop)

    def tearDown(self):
        if self.directory.exists():
            self.assertEqual(self.directory.resolve().parent, (ROOT / 'storage/mcp-google-docker').resolve())
            self.assertEqual(self.directory.name, self.plan.run)
            self.assertFalse(self.directory.is_symlink() or self.directory.is_junction())
            shutil.rmtree(self.directory)

    def phase(self, plan, images, selection, phase):
        self.calls.append(phase)
        if phase == 'exercise':
            if self.failure:
                raise self.failure
            count = 9 if selection == 'full' else 1
            return self.change({'run_id': plan.run, 'selection': selection, 'cases': count, 'passed': count,
                'failed': 0, 'flaky': 0, 'skipped': 0, 'retries': 0, 'browser_run_verified': True,
                'sql_cleanup_deferred_until_native_tree_closed': True, 'profile_accepted': False})
        if self.cleanup_failure:
            raise ValueError('synthetic cleanup failure')
        return {'run_id': plan.run, 'claimed': True, 'daemon_cleanup_verified': True, 'sql_cleanup_verified': True,
                'stopped': 3, 'remote_mutation_uncertain': False, 'volumes_retained': True, 'network_retained': True}

    def run_subject(self):
        return controller.execute(self.plan, self.images, 'synthetic', phase=self.phase)

    def test_complete_execution_orders_native_exercise_then_independent_cleanup(self):
        result = self.run_subject()
        self.assertEqual(self.calls, ['exercise', 'cleanup'])
        self.assertTrue(result['browser_run_verified'])
        self.assertFalse(result['docker_profile_accepted'])
        self.assertFalse(result['google_real_accepted'])
        self.assertFalse(result['independent_qa_accepted'])

    def test_primary_is_preserved_after_cleanup_failure(self):
        primary = RuntimeError('synthetic primary')
        self.failure, self.cleanup_failure = primary, True
        with self.assertRaises(RuntimeError) as raised:
            self.run_subject()
        self.assertIs(raised.exception, primary)
        self.assertEqual(self.calls, ['exercise', 'cleanup'])
        value = json.loads((self.directory / 'controller-summary.public.json').read_bytes())
        self.assertEqual(value['outcome'], 'failed')

    def test_unexpected_worker_field_is_not_published_as_an_exercise_result(self):
        self.change = lambda result: {**result, 'unexpected': 'synthetic'}
        with self.assertRaisesRegex(ValueError, 'native_result_invalid'):
            self.run_subject()
        self.assertEqual(self.calls, ['exercise', 'cleanup'])
        value = json.loads((self.directory / 'controller-summary.public.json').read_bytes())
        self.assertIsNone(value['exercise'])

    def test_source_drift_after_execution_still_cleans_and_never_qualifies(self):
        with patch.object(controller.qualification, 'sources', side_effect=[self.manifest, self.manifest, {}]):
            with self.assertRaisesRegex(ValueError, 'sources_changed_after_run'):
                self.run_subject()
        self.assertEqual(self.calls, ['exercise', 'cleanup'])

    def test_failed_prerequisite_has_no_directory_or_native_work(self):
        with patch.object(controller.qualification, 'prerequisite', side_effect=ValueError('synthetic refusal')):
            with self.assertRaises(ValueError):
                self.run_subject()
        self.assertFalse(self.directory.exists())
        self.assertEqual(self.calls, [])

    def test_existing_uuid_directory_cannot_be_adopted(self):
        self.directory.mkdir(parents=True)
        with self.assertRaises(FileExistsError):
            self.run_subject()
        self.assertEqual(self.calls, [])

    def test_preview_never_reads_prerequisite_or_allocates_resources(self):
        with patch.object(controller, 'execute', side_effect=AssertionError('execute forbidden')), \
             patch.object(controller, 'fresh_directory', side_effect=AssertionError('write forbidden')), \
             contextlib.redirect_stdout(io.StringIO()) as output:
            self.assertEqual(controller.main(['--case-set', 'e2e08']), 0)
        self.assertEqual(json.loads(output.getvalue())['plan']['browser_cases_expected'], 1)

    def test_bounded_phase_uses_gated_entrypoint_and_refuses_native_exit_or_foreign_frame(self):
        self.directory.mkdir(parents=True)
        for mode in ('nonzero', 'foreign', 'extra', 'valid'):
            with self.subTest(mode=mode):
                result = self.phase(self.plan, self.images, 'full', 'exercise')
                if mode == 'foreign':
                    result['run_id'] = str(uuid.uuid4())
                if mode == 'extra':
                    result['extra'] = True
                def bounded(command, **options):
                    self.assertIn('--worker', command)
                    self.assertEqual(options['timeout'], 3000)
                    return SimpleNamespace(exit_code=2 if mode == 'nonzero' else 0, stdout=json.dumps(result).encode())
                if mode == 'valid':
                    self.assertTrue(controller.bounded_phase(self.plan, self.images, 'full', 'exercise', bounded=bounded)['browser_run_verified'])
                else:
                    with self.assertRaises(ValueError):
                        controller.bounded_phase(self.plan, self.images, 'full', 'exercise', bounded=bounded)

    def test_native_probe_requires_closed_success_frame_and_uses_no_namespace(self):
        def bounded(command, **options):
            self.assertEqual(command[-1], '--owned-native-probe')
            self.assertEqual(options['timeout'], 10)
            return SimpleNamespace(exit_code=0, stdout=b'{"probe":"native-only","ok":true}\n')
        result = controller.native_probe(bounded=bounded)
        self.assertTrue(result['native_containment_verified'])
        self.assertFalse(result['sql_used'])
        self.assertFalse(result['docker_used'])
        self.assertFalse(self.directory.exists())
        with self.assertRaises(ValueError):
            controller.native_probe(bounded=lambda *a, **k: SimpleNamespace(exit_code=0, stdout=b'{"probe":"native-only","ok":true,"extra":1}'))

    def test_worker_cannot_start_without_the_native_stdin_gate(self):
        import os
        import subprocess
        result = subprocess.run([sys.executable, '-E', '-s', '-B', str(ROOT / 'scripts/browser_lab/controller.py'),
            '--owned-native-probe'], input=b'not-the-gate\n', capture_output=True, cwd=ROOT, timeout=15,
            creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(json.loads(result.stdout)['outcome'], 'refused')
        self.assertFalse(self.directory.exists())

    def test_command_deadline_survives_secondary_child_settlement_failure(self):
        self.directory.mkdir(parents=True)
        io_worker = WorkerIO(self.plan, 'exercise')
        child = SimpleNamespace(poll=lambda: None, kill=lambda: (_ for _ in ()).throw(OSError('synthetic settlement error')))
        ticks = iter(range(10))
        with patch('worker_io.subprocess.Popen', return_value=child), patch('worker_io.time.monotonic', side_effect=lambda: next(ticks)):
            with self.assertRaisesRegex(ValueError, 'command_deadline') as raised:
                io_worker.command('synthetic-deadline', ['php', '-r', 'exit(0);'], timeout=0.01)
        self.assertIn('browser_command_child_settlement_failed', raised.exception.__notes__)

    def test_cleanup_native_budget_allows_its_declared_command_deadlines(self):
        self.directory.mkdir(parents=True)
        result = self.phase(self.plan, self.images, 'full', 'cleanup')
        def bounded(command, **options):
            # Healthy completion path: web inspection/stop/reinspection170s,
            # two DB/PHP identity probes60s, helper180s, final three-identity
            # inspection90s, two stops220s, final inspection90s = 810s.
            self.assertGreaterEqual(options['timeout'], 810)
            return SimpleNamespace(exit_code=0, stdout=json.dumps(result).encode())
        self.assertTrue(controller.bounded_phase(self.plan, self.images, 'full', 'cleanup', bounded=bounded)['daemon_cleanup_verified'])


if __name__ == '__main__':
    unittest.main()
