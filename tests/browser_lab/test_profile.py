"""Browser profile preparation: pure contracts, no Docker, SQL, browser or bootstrap."""
import contextlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/browser_lab'))
from contract import BrowserPlan, LABEL, SERVICES, VOLUMES
from compose import compose
from preview import main

RUN = 'b5dc463d-f182-4d0a-896f-13337b12ea66'
IMAGES = {name: 'sha256:' + 'a' * 64 for name in SERVICES}
FLAGS = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0


class ProfileTests(unittest.TestCase):
    def setUp(self):
        self.plan = BrowserPlan(RUN)
        self.directory = ROOT / 'storage/mcp-google-docker' / RUN

    def document(self):
        return compose(self.plan, IMAGES, self.directory)

    def test_plan_has_three_owned_services_three_volumes_and_no_acceptance_claim(self):
        value = self.plan.summary()
        self.assertEqual(value['services'], list(SERVICES))
        self.assertEqual(len(value['new_volumes']), 3)
        self.assertEqual(value['browser_cases_expected'], 9)
        for name in ('tls_qualified', 'google_real_exercised', 'native_agent_clients_exercised', 'profile_accepted'):
            self.assertIs(value[name], False)

    def test_only_canonical_uuid4_service_and_volume_names_are_accepted(self):
        for run in (RUN.upper(), RUN + '\n', '../other', None, RUN.replace('4d0a', '1d0a')):
            with self.subTest(run=run), self.assertRaises(ValueError):
                BrowserPlan(run)
        with self.assertRaises(ValueError):
            self.plan.container('worker')
        with self.assertRaises(ValueError):
            self.plan.volume('../other')

    def test_no_external_network_shared_volume_or_broker_is_created(self):
        value = self.document()
        self.assertEqual(set(value['services']), set(SERVICES))
        self.assertIs(value['networks']['isolated']['internal'], True)
        self.assertEqual(set(value['volumes']), set(VOLUMES))
        for item in value['volumes'].values():
            self.assertTrue(item['name'].startswith(self.plan.project + '-'))
            self.assertNotIn('external', item)
            self.assertEqual(item['labels'], {LABEL: RUN})
        for service in value['services'].values():
            self.assertEqual(service['networks'], ['isolated'])
            self.assertEqual(service['restart'], 'no')
            self.assertEqual(service['pull_policy'], 'never')
            self.assertNotIn('build', service)

    def test_real_fpm_and_nginx_use_private_socket_and_loopback_http_only(self):
        value = self.document()['services']
        self.assertEqual(value['php']['entrypoint'], ['php-fpm', '-F'])
        self.assertEqual(value['php']['user'], '33:33')
        self.assertEqual(value['web']['ports'], ['127.0.0.1:18967:80'])
        self.assertNotIn('ports', value['mysql'])
        self.assertNotIn('ports', value['php'])
        self.assertIn('php.sock', str(value['php']['healthcheck']))
        self.assertTrue(value['web']['volumes'][0]['read_only'])

    def test_configuration_is_generated_private_and_never_host_dotenv(self):
        value = self.document()['services']
        for name in ('mysql', 'php'):
            env = value[name]['env_file']
            self.assertEqual(len(env), 1)
            self.assertEqual(Path(env[0]).parent, self.directory)
            self.assertTrue(env[0].endswith('.private.env'))
        env = value['php']['environment']
        self.assertEqual(env['DB_DATABASE'], 'emailing_testing')
        self.assertEqual(env['DB_USERNAME'], 'emailing_testing')
        self.assertEqual(env['DB_URL'], '')
        self.assertEqual(env['MAIL_MAILER'], 'log')
        self.assertEqual(env['EMAIL_EXTERNAL_ENABLED'], 'false')
        self.assertEqual(env['GOOGLE_BROWSER_PROFILE'], 'docker')
        self.assertEqual(env['GOOGLE_BROWSER_DIRECTORY'], '/var/www/emailing/storage/mcp-google-phase3/browser-' + RUN)

    def test_public_fixture_mounts_are_readonly_and_cannot_silently_create_missing_files(self):
        value = self.document()['services']
        mounts = [row for row in value['php']['volumes'] if row['type'] == 'bind']
        self.assertEqual(len(mounts), 4)
        for row in [*mounts, value['web']['volumes'][1]]:
            self.assertTrue(row['read_only'])
            self.assertFalse(row['bind']['create_host_path'])
            self.assertTrue(Path(row['source']).is_file())

    def test_mysql_health_does_not_hold_a_connection_to_the_fixture_database(self):
        test = self.document()['services']['mysql']['healthcheck']['test'][1]
        self.assertIn('SELECT 1', test)
        self.assertNotIn('emailing_testing', test)
        self.assertNotIn('-D', test)

    def test_missing_tagged_or_extra_images_refuse(self):
        for images in ({}, {**IMAGES, 'php': 'latest'}, {**IMAGES, 'php': None}, {**IMAGES, 'worker': IMAGES['php']}):
            with self.subTest(images=images), self.assertRaises(ValueError):
                compose(self.plan, images, self.directory)

    def test_other_output_directory_and_run_are_refused(self):
        for directory in (ROOT / 'storage/other' / RUN, self.directory / 'child', Path(RUN)):
            with self.subTest(directory=str(directory)), self.assertRaises(ValueError):
                compose(self.plan, IMAGES, directory)

    def test_preview_is_readonly_and_does_not_spawn(self):
        with patch('subprocess.run', side_effect=AssertionError('process forbidden')), \
             patch.object(Path, 'mkdir', side_effect=AssertionError('write forbidden')), \
             contextlib.redirect_stdout(io.StringIO()) as output:
            self.assertEqual(main(['--run-id', RUN]), 0)
        result = json.loads(output.getvalue())
        self.assertEqual(result['mode'], 'preview-only')
        self.assertFalse(result['filesystem_written'])
        self.assertFalse(result['docker_used'])

    def test_php_boundary_cases_do_not_load_application_or_configuration(self):
        result = subprocess.run(['php', '-n', '-d', 'zend.exception_ignore_args=1', 'tests/browser_lab/php/check_boundary.php'],
            cwd=ROOT, capture_output=True, timeout=15, creationflags=FLAGS)
        self.assertEqual(result.returncode, 0, 'Pure PHP browser guard failed.')
        value = json.loads(result.stdout)
        self.assertGreaterEqual(value['checks'], 35)
        self.assertFalse(value['bootstrap_used'])
        self.assertFalse(value['sql_used'])

    def test_fixture_entrypoint_rejects_unknown_action_before_vendor_or_sql(self):
        result = subprocess.run(['php', '-n', '-d', 'zend.exception_ignore_args=1',
            'tests/support/google-browser-fixtures.php', 'unknown'], cwd=ROOT, capture_output=True,
            timeout=15, creationflags=FLAGS)
        self.assertEqual(result.returncode, 2)
        self.assertEqual(result.stdout, b'')
        self.assertEqual(result.stderr, b'Browser fixture descriptor refused before bootstrap.\n')

    def test_node_contracts_use_only_fake_processes_and_temporary_synthetic_files(self):
        result = subprocess.run(['node', '--test', '--test-reporter=tap', 'tests/browser_lab/node/runtime.test.mjs'],
            cwd=ROOT, capture_output=True, timeout=15, creationflags=FLAGS)
        self.assertEqual(result.returncode, 0, 'Pure Node browser transport failed.')
        self.assertIsNotNone(re.search(rb'# tests 8\r?\n', result.stdout))
        self.assertIsNotNone(re.search(rb'# fail 0\r?\n', result.stdout))

    def test_configuration_helper_exercises_generated_files_without_reading_private_bodies(self):
        result = subprocess.run(['node', '--test', '--test-reporter=tap', 'tests/browser_lab/node/helper.test.mjs'],
            cwd=ROOT, capture_output=True, timeout=15, creationflags=FLAGS)
        self.assertEqual(result.returncode, 0, 'Synthetic configuration tests failed.')
        self.assertIsNotNone(re.search(rb'# tests 4\r?\n', result.stdout))
        self.assertIsNotNone(re.search(rb'# fail 0\r?\n', result.stdout))

    def test_shared_request_barrier_waits_for_readers_and_denies_late_requests(self):
        result = subprocess.run(['php', '-n', '-d', 'zend.exception_ignore_args=1', 'tests/browser_lab/php/check_lease.php'],
            cwd=ROOT, capture_output=True, timeout=15, creationflags=FLAGS)
        self.assertEqual(result.returncode, 0, 'Filesystem request barrier tests failed.')
        value = json.loads(result.stdout)
        self.assertEqual(value['checks'], 15)
        self.assertEqual(value['shared_readers_observed'], 2)
        self.assertTrue(value['late_request_denied'])
        self.assertFalse(value['sql_used'])


if __name__ == '__main__':
    unittest.main()
