"""Execute an attributed Docker browser run; no effects without --execute."""
import argparse
import base64
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import time
import uuid
import urllib.request

from contract import BrowserPlan, SERVICES, image_id, require
from docker_profile import DockerProfile
import qualification
from results import titles, verify_report
from worker_io import ROOT, WorkerIO

sys.path.insert(0, str(ROOT / 'scripts/mcp_profile'))
from profile_process import GATE, run_bounded

HELPER = 'tests/support/google-docker-helper.mjs'


def reserve(port):
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        server.bind(('127.0.0.1', port))
        server.listen(1)
    except BaseException:
        server.close()
        raise
    return server


def wait_web(*, timeout=30, clock=time.monotonic, pause=time.sleep):
    class NoRedirects(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *arguments, **options):
            return None
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirects())
    deadline = clock() + timeout
    while clock() < deadline:
        try:
            with opener.open('http://127.0.0.1:18967/login', timeout=min(2, max(0.01, deadline - clock()))) as response:
                if response.status == 200:
                    return
        except (OSError, ValueError):
            pass  # Bounded readiness wait; failure is explicit at the deadline.
        pause(min(0.25, max(0, deadline - clock())))
    require(False, 'browser_web_not_ready')


class Exercise:
    def __init__(self, io, images, selection):
        self.io, self.selection = io, selection
        self.profile = DockerProfile(io, images)

    def run(self):
        io, p = self.io, self.profile
        frozen = io.public('source-manifest.public.json')
        require(qualification.sources() == frozen, 'browser_sources_changed_before_worker')
        cli = ROOT / 'node_modules/@playwright/test/cli.js'
        require(cli.is_file(), 'browser_dependencies_not_installed')
        sockets, proxy, streams = [], None, []
        result, primary = None, None
        try:
            for port in (18967, 18743):
                sockets.append(reserve(port))
            p.acquire()
            env = {**os.environ, 'BROWSER_CONTROLLER_GATED': '1'}
            configured = io.command('generate-synthetic-configuration', ['node', HELPER, 'configure', io.plan.run], env=env, timeout=30)
            require(json.loads(configured.stdout) == {'configured': True, 'existing_configuration_read': False,
                'secrets_returned': False}, 'browser_configuration_result_invalid')
            for kind in ('stdout', 'stderr'):
                streams.append((io.directory / ('proxy-' + kind + '.private.txt')).open('xb'))
            proxy = subprocess.Popen(['node', HELPER, 'proxy', io.plan.run], cwd=ROOT, env=env,
                stdin=subprocess.PIPE, stdout=streams[0], stderr=streams[1],
                creationflags=subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0)
            ready = io.directory / 'proxy-ready.public.json'
            deadline = time.monotonic() + 15
            while not ready.exists():
                require(proxy.poll() is None and time.monotonic() < deadline, 'browser_deny_proxy_not_ready')
                time.sleep(0.05)
            require(ready.is_file() and not ready.is_symlink() and ready.stat().st_nlink == 1 and ready.stat().st_size <= 1024
                    and json.loads(ready.read_bytes()) == {'run_id': io.plan.run, 'bound': True, 'port': 18968}, 'browser_deny_proxy_unattributed')
            p.up(['mysql', 'php'])
            p.wait()
            qualification.verify_application(p, frozen)
            p.exec_php('initialize-own-fixture-directory', ['-r', '$p=getenv("GOOGLE_BROWSER_DIRECTORY");if(is_dir($p)||is_link($p)||!mkdir($p,0700,true)){exit(2);}'], timeout=30)
            io.save('fixture-state.public.json', {'run_id': io.plan.run, 'setup_started': True, 'setup_complete': False})
            setup = io.command('prepare-owned-sql-fixtures', ['node', HELPER, 'setup', io.plan.run], env=p.environment(), timeout=240)
            require(json.loads(setup.stdout) == {'action': 'setup', 'fixture_complete': True}, 'browser_fixture_setup_unverified')
            io.save('fixture-state.public.json', {'run_id': io.plan.run, 'setup_started': True, 'setup_complete': True})
            sockets[0].close()
            p.up(['web'])
            qualification.verify_images(p, frozen)
            wait_web()
            sockets[1].close()
            expected = titles(self.selection)
            command = ['node', str(cli), 'test', '--config', 'tests/support/google-docker-playwright.config.mjs',
                       '--reporter=json', '--output', str(io.directory / 'browser-results')]
            if self.selection == 'e2e08':
                command += ['--grep', 'MCP E2E-08 ']
            browser_env = p.environment()
            browser_env.pop('PLAYWRIGHT_JSON_OUTPUT_NAME', None)
            browser_env.pop('PLAYWRIGHT_JSON_OUTPUT_DIR', None)
            browser_env.pop('PLAYWRIGHT_JSON_OUTPUT_FILE', None)
            observed = io.command('playwright-owned-browser', command, env=browser_env, timeout=1200, check=False, limit=8 * 1048576)
            require(observed.returncode == 0, 'browser_playwright_failed')
            result = verify_report(observed.stdout, expected)
            require(proxy.poll() is None, 'browser_deny_proxy_exited')
            require(qualification.sources() == frozen, 'browser_sources_changed_during_worker')
            result.update(run_id=io.plan.run, selection=self.selection, browser_run_verified=True,
                          sql_cleanup_deferred_until_native_tree_closed=True, profile_accepted=False)
            io.save('exercise.public.json', result)
        except BaseException as error:
            primary = error
            raise
        finally:
            for handle in sockets:
                handle.close()
            try:
                if proxy and proxy.poll() is None:
                    proxy.stdin.write(b'stop\n')
                    proxy.stdin.flush()
                    proxy.stdin.close()
                    proxy.wait(timeout=5)
            except BaseException:
                if primary is None:
                    raise
                primary.add_note('browser_proxy_close_failed')
            finally:
                for stream in streams:
                    stream.close()
        return result


def cleanup(io, images):
    p = DockerProfile(io, images)
    if not p.recover_claim():
        result = {'run_id': io.plan.run, 'claimed': False, 'daemon_cleanup_verified': True,
                  'sql_cleanup_verified': True, 'stopped': 0, 'remote_mutation_uncertain': False,
                  'volumes_retained': True, 'network_retained': True}
        io.save('cleanup.public.json', result)
        return result
    primary, sql_clean, stopped = None, False, None
    uncertain = True
    try:
        mutation = io.public('mutation.public.json', optional=True)
        if mutation is None:
            uncertain = False
        else:
            require(isinstance(mutation, dict) and set(mutation) == {'run_id', 'operations'}
                    and mutation['run_id'] == io.plan.run and isinstance(mutation['operations'], list)
                    and 1 <= len(mutation['operations']) <= 2, 'browser_mutation_journal_invalid')
            for index, row in enumerate(mutation['operations']):
                require(isinstance(row, dict) and set(row) == {'operation', 'services', 'complete'}
                        and row['operation'] == 'compose-up' and type(row['complete']) is bool
                        and row['services'] == (['mysql', 'php'] if index == 0 else ['web']), 'browser_mutation_journal_invalid')
            uncertain = any(row['complete'] is not True for row in mutation['operations'])
        fixture = io.public('fixture-state.public.json', optional=True)
        web = p.owned('web', optional=True)
        if web and web['running']:
            io.command('stop-web-before-sql-cleanup', ['docker', 'container', 'stop', '--time', '90', web['id']], timeout=110)
            require(p.owned('web')['running'] is False, 'browser_web_not_stopped')
        if fixture is None:
            sql_clean = True  # SQL was never allowed to start before its journal.
        else:
            require(fixture.get('run_id') == io.plan.run and fixture.get('setup_started') is True, 'browser_fixture_journal_invalid')
            require(p.owned('mysql')['running'] is True, 'browser_cleanup_mysql_stopped')
            observed = io.command('cleanup-owned-sql-fixtures', ['node', HELPER, 'cleanup', io.plan.run], env=p.environment(), timeout=180)
            require(json.loads(observed.stdout) == {'action': 'cleanup', 'fixture_complete': True}, 'browser_sql_cleanup_unverified')
            sql_clean = True
    except BaseException as error:
        primary = error
    finally:
        try:
            stopped = p.stop_all()
        except BaseException as error:
            if primary is None:
                primary = error
            else:
                primary.add_note('browser_owned_daemon_stop_failed')
        result = {'run_id': io.plan.run, 'claimed': True, 'daemon_cleanup_verified': primary is None and not uncertain,
                  'sql_cleanup_verified': sql_clean, 'stopped': stopped['stopped'] if stopped else None,
                  'remote_mutation_uncertain': uncertain, 'volumes_retained': True, 'network_retained': True}
        if primary is not None:
            result['failure_class'] = type(primary).__name__
        try:
            io.save('cleanup.public.json', result)
        except BaseException:
            if primary is None:
                raise
            primary.add_note('browser_cleanup_receipt_unavailable')
    if primary is not None:
        raise primary
    require(not uncertain, 'browser_remote_mutation_unresolved')
    return result


def fresh_directory(plan):
    current = ROOT
    for part in ('storage', 'mcp-google-docker'):
        current /= part
        require(not current.is_symlink() and not current.is_junction(), 'browser_directory_refused')
        current.mkdir(mode=0o700, exist_ok=True)
        require(current.is_dir() and current.resolve() == current, 'browser_directory_refused')
    directory = current / plan.run
    directory.mkdir(mode=0o700)
    return directory


def bounded_phase(plan, images, selection, phase, *, bounded=run_bounded):
    payload = {'run': plan.run, 'images': images, 'selection': selection, 'phase': phase}
    encoded = base64.b64encode(json.dumps(payload).encode('ascii')).decode('ascii')
    receipt = {'phase': phase, 'worker_exit': None, 'native_containment_verified': False, 'result_verified': False}
    primary = None
    try:
        observed = bounded([sys.executable, '-E', '-s', '-B', str(Path(__file__).resolve()), '--worker', encoded],
                           cwd=ROOT, timeout=3000 if phase == 'exercise' else 1200, max_output_bytes=65536)
        receipt.update(worker_exit=observed.exit_code, native_containment_verified=True)
        require(observed.exit_code == 0, 'browser_native_phase_failed')
        result = json.loads(observed.stdout)
        validate_phase_result(result, plan, selection, phase)
        receipt['result_verified'] = True
        return result
    except BaseException as error:
        primary = error
        receipt['failure_class'] = type(error).__name__
        raise
    finally:
        try:
            WorkerIO(plan, phase).save('native-' + phase + '.public.json', receipt)
        except BaseException:
            if primary is None:
                raise
            primary.add_note('browser_native_phase_receipt_unavailable')


def validate_phase_result(result, plan, selection, phase):
    require(isinstance(result, dict) and result.get('run_id') == plan.run, 'browser_native_result_invalid')
    if phase == 'exercise':
        keys = {'cases', 'passed', 'failed', 'flaky', 'skipped', 'retries', 'run_id', 'selection', 'browser_run_verified',
                'sql_cleanup_deferred_until_native_tree_closed', 'profile_accepted'}
        require(set(result) == keys and result['selection'] == selection and result['browser_run_verified'] is True
                and result['sql_cleanup_deferred_until_native_tree_closed'] is True and result['profile_accepted'] is False,
                'browser_native_result_invalid')
        count = 9 if selection == 'full' else 1
        for key, value in {'cases': count, 'passed': count, 'failed': 0, 'flaky': 0, 'skipped': 0, 'retries': 0}.items():
            require(type(result[key]) is int and result[key] == value, 'browser_native_result_invalid')
    else:
        require(phase == 'cleanup' and set(result) == {'run_id', 'claimed', 'daemon_cleanup_verified', 'sql_cleanup_verified',
                'stopped', 'remote_mutation_uncertain', 'volumes_retained', 'network_retained'}, 'browser_native_result_invalid')
        require(all(type(result[key]) is bool for key in result if key not in ('run_id', 'stopped'))
                and type(result['stopped']) is int and 0 <= result['stopped'] <= 3
                and result['daemon_cleanup_verified'] is True and result['sql_cleanup_verified'] is True
                and result['remote_mutation_uncertain'] is False and result['volumes_retained'] is True
                and result['network_retained'] is True, 'browser_native_result_invalid')


def execute(plan, images, prerequisite_directory, selection='full', *, phase=bounded_phase):
    require(isinstance(images, dict) and set(images) == set(SERVICES), 'browser_three_images_required')
    for value in images.values():
        image_id(value)
    expected = titles(selection)
    frozen = qualification.sources()
    accepted = qualification.prerequisite(prerequisite_directory)
    require(qualification.sources() == frozen, 'browser_sources_changed_during_prerequisite')
    qualification.app_sources(frozen)
    fresh_directory(plan)
    io = WorkerIO(plan, 'exercise')
    io.save('source-manifest.public.json', frozen)
    primary, exercised, settled = None, None, None
    try:
        observed = phase(plan, images, selection, 'exercise')
        validate_phase_result(observed, plan, selection, 'exercise')
        require(observed.get('browser_run_verified') is True and observed.get('selection') == selection
                and type(observed.get('passed')) is int and observed['passed'] == len(expected), 'browser_exercise_not_verified')
        exercised = observed
    except BaseException as error:
        primary = error
    finally:
        # run_bounded has closed the first native job before this phase starts.
        try:
            observed = phase(plan, images, selection, 'cleanup')
            validate_phase_result(observed, plan, selection, 'cleanup')
            require(observed.get('daemon_cleanup_verified') is True and observed.get('sql_cleanup_verified') is True,
                    'browser_cleanup_not_verified')
            settled = observed
        except BaseException as error:
            if primary is None:
                primary = error
            else:
                primary.add_note('browser_independent_cleanup_phase_failed')
        stable = False
        try:
            stable = qualification.sources() == frozen
            require(stable, 'browser_sources_changed_after_run')
        except BaseException as error:
            if primary is None:
                primary = error
            else:
                primary.add_note('browser_source_verification_failed')
        result = {'run_id': plan.run, 'selection': selection, 'outcome': 'verified' if primary is None else 'failed',
                  'browser_run_verified': primary is None, 'source_bytes_stable': stable,
                  'exercise': exercised, 'cleanup': settled, 'prerequisite': accepted,
                  'docker_profile_accepted': False, 'google_real_accepted': False, 'independent_qa_accepted': False}
        if primary:
            result['failure_class'] = type(primary).__name__
        try:
            io.save('controller-summary.public.json', result)
        except BaseException:
            if primary is None:
                raise
            primary.add_note('browser_controller_receipt_unavailable')
    if primary is not None:
        raise primary
    return result


def worker(encoded):
    require(len(encoded) <= 8192, 'browser_worker_descriptor_limit')
    value = json.loads(base64.b64decode(encoded, validate=True))
    require(isinstance(value, dict) and set(value) == {'run', 'images', 'selection', 'phase'}, 'browser_worker_descriptor_refused')
    plan = BrowserPlan(value['run'])
    require(value['phase'] in ('exercise', 'cleanup') and value['selection'] in ('full', 'e2e08'), 'browser_worker_phase_refused')
    io = WorkerIO(plan, value['phase'])
    if value['phase'] == 'exercise':
        return Exercise(io, value['images'], value['selection']).run()
    return cleanup(io, value['images'])


def native_probe(*, bounded=run_bounded):
    observed = bounded([sys.executable, '-E', '-s', '-B', str(Path(__file__).resolve()), '--owned-native-probe'],
                       cwd=ROOT, timeout=10, max_output_bytes=4096)
    require(observed.exit_code == 0 and json.loads(observed.stdout) == {'probe': 'native-only', 'ok': True},
            'browser_native_probe_failed')
    return {'native_containment_verified': True, 'sql_used': False, 'docker_used': False, 'profile_accepted': False}


def main(arguments=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--execute', action='store_true')
    parser.add_argument('--probe-native', action='store_true')
    parser.add_argument('--run-id')
    parser.add_argument('--prerequisite')
    parser.add_argument('--case-set', choices=['full', 'e2e08'], default='full')
    parser.add_argument('--image', action='append', default=[], metavar='SERVICE=sha256:ID')
    args = parser.parse_args(arguments)
    plan = BrowserPlan(args.run_id or str(uuid.uuid4()))
    if args.probe_native:
        require(not args.image and args.prerequisite is None and args.case_set == 'full', 'browser_probe_arguments_refused')
        print(json.dumps(native_probe() if args.execute else {'mode': 'preview-only', 'probe': 'native',
            'filesystem_written': False, 'sql_used': False, 'docker_used': False}))
        return 0
    if not args.execute:
        summary = plan.summary()
        summary['browser_cases_expected'] = 9 if args.case_set == 'full' else 1
        print(json.dumps({'mode': 'preview-only', 'plan': summary, 'selection': args.case_set,
                          'filesystem_written': False, 'docker_used': False, 'sql_used': False}))
        return 0
    images = {}
    for item in args.image:
        pair = item.split('=', 1)
        require(len(pair) == 2 and pair[0] in SERVICES and pair[0] not in images, 'browser_image_argument_refused')
        images[pair[0]] = image_id(pair[1])
    print(json.dumps(execute(plan, images, args.prerequisite, args.case_set)))
    return 0


if __name__ == '__main__':
    try:
        if sys.argv[1:] == ['--owned-native-probe']:
            require(sys.stdin.buffer.readline(64) == GATE, 'browser_native_gate_refused')
            print(json.dumps({'probe': 'native-only', 'ok': True}))
            raise SystemExit(0)
        if len(sys.argv) == 3 and sys.argv[1] == '--worker':
            require(sys.stdin.buffer.readline(64) == GATE, 'browser_native_gate_refused')
            print(json.dumps(worker(sys.argv[2])))
            raise SystemExit(0)
        raise SystemExit(main())
    except Exception as error:
        print(json.dumps({'outcome': 'refused', 'failure_class': type(error).__name__, 'docker_profile_accepted': False}))
        raise SystemExit(2)
