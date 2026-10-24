"""Owned delivery QA runtime: four Compose services and actual native Apache/PHP.

Run through an evidence producer with T16_EVIDENCE_DIRECTORY set. Default settles
all owned resources. --hold leaves review surfaces up until an owned stop file is
created, then performs the same settlement. Human M-01 remains a separate verdict.
"""
import argparse
from datetime import datetime, timezone
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import secrets
import re
import shutil
import socket
import subprocess
import sys
import tarfile
import time
import urllib.error
import urllib.request
import urllib.parse
import uuid
import zipfile

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(os.environ['T16_EVIDENCE_DIRECTORY']).resolve()
OWN = uuid.uuid4().hex
PROJECT = 'emailing-t16-' + OWN
PHP_IMAGE = 'emailing-t16/php:' + OWN
WEB_IMAGE = 'emailing-t16/web:' + OWN
children = []
commands = []
cleanup_errors = []
sequence = 0


def sha(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def save(name, document):
    (OUT / name).write_text(json.dumps(document, indent=2), encoding='utf-8')


def command(label, argv, *, env=None, data=None, binary=False, check=True):
    global sequence
    sequence += 1
    stem = f'{sequence:03d}-' + label
    started = datetime.now(timezone.utc).isoformat()
    save(stem + '-request.json', {'command': argv, 'started_at': started})
    with (OUT / (stem + '-stdout' + ('.tar' if binary else '.txt'))).open('wb') as stdout, (OUT / (stem + '-stderr.txt')).open('wb') as stderr:
        process = subprocess.Popen(argv, cwd=ROOT, env=env, stdin=subprocess.PIPE if data is not None else subprocess.DEVNULL, stdout=stdout, stderr=stderr)
        if data is not None:
            process.stdin.write(json.dumps(data).encode())
            process.stdin.close()
        code = process.wait()
    stdout_path = OUT / (stem + '-stdout' + ('.tar' if binary else '.txt'))
    receipt = {'command': argv, 'pid': process.pid, 'exit': code, 'started_at': started, 'ended_at': datetime.now(timezone.utc).isoformat(), 'stdout_sha256': sha(stdout_path), 'stderr_sha256': sha(OUT / (stem + '-stderr.txt'))}
    save(stem + '-receipt.json', receipt)
    commands.append(receipt)
    if check and code != 0:
        raise RuntimeError('Owned command failed: ' + label)
    return stdout_path if binary else stdout_path.read_text(encoding='utf-8-sig')


def launch(label, argv, env):
    out = (OUT / (label + '-stdout.txt')).open('wb')
    err = (OUT / (label + '-stderr.txt')).open('wb')
    process = subprocess.Popen(argv, cwd=ROOT, env=env, stdout=out, stderr=err)
    save(label + '-request.json', {'command': argv, 'pid': process.pid, 'started_at': datetime.now(timezone.utc).isoformat()})
    children.append((label, process, out, err, argv))
    return process


def stop_children():
    for label, process, out, err, argv in reversed(children):
        if process.poll() is None:
            process.terminate()  # Exact Popen handle created by this run only.
        code = process.wait()
        out.close()
        err.close()
        save(label + '-receipt.json', {'command': argv, 'pid': process.pid, 'exit': code, 'terminal': True, 'forced_owned_stop': code != 0, 'stdout_sha256': sha(OUT / (label + '-stdout.txt')), 'stderr_sha256': sha(OUT / (label + '-stderr.txt'))})


def port():
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        return listener.getsockname()[1]


def dotenv(path):
    values = {}
    for line in path.read_text(encoding='utf-8-sig').splitlines():
        if not line.strip() or line.lstrip().startswith('#') or '=' not in line:
            continue
        name, value = line.split('=', 1)
        values[name] = value.strip().strip('"').strip("'")
    assert values.get('DB_DATABASE') == values.get('DB_USERNAME') == 'emailing_testing'
    assert not values.get('DB_URL')
    return values


def request(origin, path, data=None):
    with urllib.request.urlopen(urllib.request.Request(origin + path, data=data, method='POST' if data is not None else 'GET'), timeout=10) as response:
        return response.status, response.read()


def await_health(origin, process=None):
    deadline = time.monotonic() + 45
    while time.monotonic() < deadline:
        if process is not None and process.poll() is not None:
            raise RuntimeError('Owned native server exited before readiness.')
        try:
            if request(origin, '/up')[0] == 200:
                return
        except (OSError, urllib.error.HTTPError):
            pass
        time.sleep(.1)
    raise RuntimeError('Owned server readiness deadline exceeded.')


def public_unsubscribe(label, url):
    # Same ordinary form and cookie flow as a public browser; CSRF stays enabled.
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    with opener.open(url, timeout=10) as response:
        assert response.status == 200
        html = response.read().decode('utf-8')
    token = re.search(r'name="_token" value="([^"]+)"', html)
    assert token, 'Public confirmation form must include CSRF.'
    data = urllib.parse.urlencode({'_token': token[1]}).encode()
    altered = url[:-1] + ('a' if url[-1] != 'a' else 'b')
    try:
        opener.open(urllib.request.Request(altered, data=data), timeout=10)
        raise AssertionError('Manipulated capability must be rejected.')
    except urllib.error.HTTPError as error:
        assert error.code == 404
    statuses = []
    for _ in range(2):
        with opener.open(urllib.request.Request(url, data=data), timeout=10) as response:
            statuses.append(response.status)
            assert response.status == 200
    save(label + '.json', {'get_form': 200, 'tampered_token': 404, 'confirmation_and_replay': statuses, 'capability_url_sha256': hashlib.sha256(url.encode()).hexdigest(), 'csrf_enabled': True})


def finite_trigger(label, origin, secret):
    url = origin + '/internal/cronicle/trigger'
    data = json.dumps({'action': 'materialize'}).encode()
    try:
        urllib.request.urlopen(urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json'}), timeout=15)
        raise AssertionError('Finite trigger requires its dedicated credential.')
    except urllib.error.HTTPError as error:
        assert error.code == 401
    facts = {'unauthenticated': 401, 'actions': {}}
    for action in ['materialize', 'dispatch']:
        data = json.dumps({'action': action}).encode()
        with urllib.request.urlopen(urllib.request.Request(url, data=data, headers={'Content-Type': 'application/json', 'Authorization': 'Bearer ' + secret}), timeout=20) as response:
            result = json.load(response)
            assert response.status == 200 and result['status'] == 'processed'
            assert result['emails_completed'] is None
            assert result['deliveries_created'] == 0 and result['outbox_claims'] == 0
            facts['actions'][action] = {'status': 200, 'created': 0, 'claims': 0, 'emails_completed': None}
    save(label + '.json', facts)


def zip_tree(destination, roots):
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
        for name in roots:
            root = ROOT / name
            for path in sorted(root.rglob('*')):
                if path.is_symlink():
                    raise RuntimeError('Linked artifact source refused.')
                if path.is_file():
                    archive.write(path, path.relative_to(ROOT).as_posix())


def tar_zip(tar_path, destination, prefix):
    with tarfile.open(tar_path) as archive, zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as output:
        for member in archive.getmembers():
            if member.isdir():
                continue
            if not member.isfile() or '..' in Path(member.name).parts:
                raise RuntimeError('Nonregular build artifact refused.')
            name = prefix + member.name
            output.writestr(name, archive.extractfile(member).read())


def expected(label, build, dependencies, php_version, platform):
    target = OUT / (label + '-expected.json')
    command(label + '-expected', [sys.executable, 'tests/support/runtime_expected_manifest.py', '--source', str(OUT / 'sources.zip'), '--build', str(build), '--dependencies', str(dependencies), '--php-version', php_version, '--platform', platform, '--reporter', 'tests/support/runtime-source-inventory.php', '--output', str(target)])
    return target


def compare(label, artifact, observation):
    command(label + '-comparison', [sys.executable, 'tests/support/runtime_inventory_compare.py', '--expected', str(artifact), '--actual', str(observation)])


def protect(label, path):
    if os.name == 'nt':
        command(label, ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', 'tests/support/protect-qa-file.ps1', '-Path', str(path)])
    else:
        path.chmod(0o600)


def fixture(label, mode, owner, env, *, docker=False, password=None):
    data = {'mode': mode, 'owner': owner}
    if password:
        data['password'] = password
    argv = ['docker', 'exec', '-i', PROJECT + '-emailing-php-1', 'php', '-d', 'zend.exception_ignore_args=1', 'tests/support/delivery-qa.php'] if docker else ['php', '-d', 'zend.exception_ignore_args=1', 'tests/support/delivery-qa.php']
    result = json.loads(command(label, argv, env=env, data=data))
    assert result['identity'] == {'db': 'emailing_testing', 'db_user': 'emailing_testing@%'}
    assert result['required_tables_present']
    return result


def relay_messages(owner):
    with urllib.request.urlopen('http://127.0.0.1:18025/api/v1/search?query=to:t16-' + owner + '@example.test', timeout=5) as response:
        return json.load(response)['messages']


def settle_relay(owner):
    messages = relay_messages(owner)
    for message in messages:
        identifier = message['ID']
        with urllib.request.urlopen('http://127.0.0.1:18025/api/v1/message/' + identifier, timeout=5) as response:
            captured = json.load(response)
        assert captured['To'][0]['Address'] == 't16-' + owner + '@example.test'
        data = json.dumps({'IDs': [identifier]}).encode()
        with urllib.request.urlopen(urllib.request.Request('http://127.0.0.1:18025/api/v1/messages', data=data, method='DELETE', headers={'Content-Type': 'application/json'}), timeout=5) as response:
            assert response.status == 200
    assert relay_messages(owner) == []
    return [m['ID'] for m in messages]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--hold', action='store_true')
    args = parser.parse_args()
    base = dotenv(ROOT / '.env.testing')
    php_dir = Path(shutil.which('php')).parent
    apache_roots = sorted((Path('C:/laragon/bin/apache')).glob('httpd-*/bin/httpd.exe'))
    apache = next((p for p in reversed(apache_roots) if '2.4.66' in str(p)), apache_roots[-1])
    assert (php_dir / 'php8apache2_4.dll').is_file()
    native_owner = uuid.uuid4().hex
    docker_owner = uuid.uuid4().hex
    native_origin = 'http://127.0.0.1:' + str(port())
    docker_origin = 'http://127.0.0.1:' + str(port())
    password = secrets.token_urlsafe(24)
    cronicle_secret = secrets.token_hex(32)
    native_env = os.environ.copy()
    native_env.update(base)
    native_env.update(APP_ENV='local', APP_DEBUG='false', APP_URL=native_origin, DB_DATABASE='emailing_testing', DB_USERNAME='emailing_testing', DB_URL='', SESSION_DRIVER='file', CACHE_STORE='array', QUEUE_CONNECTION='rabbitmq', RABBITMQ_QUEUE='emailing.t16.' + native_owner + '.jobs', DELIVERY_EXCHANGE='emailing.t16.' + native_owner, DELIVERY_QUEUE='emailing.t16.' + native_owner, DELIVERY_FAILURE_QUEUE='emailing.t16.' + native_owner + '.failed', DELIVERY_RELAY_POOL='t16-' + native_owner, MAILPIT_SMTP_HOST='127.0.0.1', MAILPIT_SMTP_PORT='11025', MAILPIT_API_URL='http://127.0.0.1:18025')
    php_ini = OUT / 'native-php-ini'
    php_ini.mkdir()
    (php_ini / 'sockets.ini').write_text('extension=sockets\nzend.exception_ignore_args=1\n', encoding='ascii')
    native_env['PHP_INI_SCAN_DIR'] = str(php_ini)
    native_env['SESSION_COOKIE'] = 'emailing-t16-' + native_owner
    native_env['CRONICLE_TRIGGER_SECRET'] = cronicle_secret
    docker_env = dict(base)
    docker_env.update(APP_ENV='local', APP_DEBUG='false', APP_URL=docker_origin, DB_HOST='mysql', DB_PORT='3306', DB_DATABASE='emailing_testing', DB_USERNAME='emailing_testing', DB_URL='', SESSION_DRIVER='file', CACHE_STORE='array', QUEUE_CONNECTION='rabbitmq', RABBITMQ_HOST='rabbitmq', RABBITMQ_QUEUE='emailing.t16.' + docker_owner + '.jobs', DELIVERY_EXCHANGE='emailing.t16.' + docker_owner, DELIVERY_QUEUE='emailing.t16.' + docker_owner, DELIVERY_FAILURE_QUEUE='emailing.t16.' + docker_owner + '.failed', DELIVERY_RELAY_POOL='t16-' + docker_owner, MAILPIT_SMTP_HOST='mailpit', MAILPIT_SMTP_PORT='1025', MAILPIT_API_URL='http://mailpit:8025')
    docker_env['SESSION_COOKIE'] = 'emailing-t16-' + docker_owner
    docker_env['CRONICLE_TRIGGER_SECRET'] = cronicle_secret
    env_file = OUT / 'qa.private.env'
    env_file.write_text('\n'.join(key + '=' + value for key, value in docker_env.items()) + '\n', encoding='utf-8')
    protect('private-env-acl', env_file)
    save('ownership.json', {'owner': OWN, 'project': PROJECT, 'native_owner': native_owner, 'docker_owner': docker_owner, 'images': [PHP_IMAGE, WEB_IMAGE], 'services': ['emailing-web', 'emailing-php', 'emailing-worker', 'emailing-jobs'], 'native_web_server': 'Apache with PHP module', 'native_worker_mode': 'bounded actual domain/jobs consumers'})
    native_initialized = False
    docker_initialized = False
    native_init_attempted = False
    docker_init_attempted = False
    docker_started = False
    artifact_name = PROJECT + '-artifact'
    artifact_created = False
    compose = None
    results = {}
    try:
        command('build-php', ['docker', 'build', '-f', 'docker/Dockerfile', '--target', 'app', '--iidfile', str(OUT / 'php-image-id.txt'), '-t', PHP_IMAGE, '.'])
        command('build-web', ['docker', 'build', '-f', 'docker/Dockerfile', '--target', 'web', '--iidfile', str(OUT / 'web-image-id.txt'), '-t', WEB_IMAGE, '.'])
        command('artifact-create', ['docker', 'create', '--name', artifact_name, PHP_IMAGE])
        artifact_created = True
        dependency_tar = command('artifact-vendor', ['docker', 'cp', artifact_name + ':/var/www/emailing/vendor', '-'], binary=True)
        cache_tar = command('artifact-bootstrap-cache', ['docker', 'cp', artifact_name + ':/var/www/emailing/bootstrap/cache', '-'], binary=True)
        build_tar = command('artifact-build', ['docker', 'cp', artifact_name + ':/var/www/emailing/public/build', '-'], binary=True)
        tar_zip(dependency_tar, OUT / 'docker-vendor.zip', '')
        tar_zip(cache_tar, OUT / 'docker-cache.zip', 'bootstrap/')
        with zipfile.ZipFile(OUT / 'docker-dependencies.zip', 'w', zipfile.ZIP_DEFLATED) as output:
            for source in ['docker-vendor.zip', 'docker-cache.zip']:
                with zipfile.ZipFile(OUT / source) as archive:
                    for name in archive.namelist():
                        output.writestr(name, archive.read(name))
        tar_zip(build_tar, OUT / 'docker-build.zip', 'public/')
        image_php_version = command('image-php-version', ['docker', 'run', '--rm', '--name', PROJECT + '-php-version', '--network', 'none', PHP_IMAGE, 'php', '-n', '-r', 'echo PHP_VERSION;']).strip()
        docker_expected = expected('docker', OUT / 'docker-build.zip', OUT / 'docker-dependencies.zip', image_php_version, 'Linux')
        fixture('native-schema', 'schema', native_owner, native_env)
        native_init_attempted = True
        fixture('native-init', 'init', native_owner, native_env, password=password)
        native_initialized = True
        protect('native-access-acl', ROOT / 'storage/app/private' / ('t16-' + native_owner + '.json'))
        zip_tree(OUT / 'native-dependencies.zip', ['vendor', 'bootstrap/cache'])
        zip_tree(OUT / 'native-build.zip', ['public/build'])
        native_version = command('native-php-version', ['php', '-n', '-r', 'echo PHP_VERSION;'], env=native_env).strip()
        native_expected = expected('native', OUT / 'native-build.zip', OUT / 'native-dependencies.zip', native_version, 'Windows')
        native_observation = OUT / 'native-inventory.json'
        native_observation.write_text(command('native-cli-inventory', ['php', '-n', 'tests/support/runtime-source-inventory.php'], env=native_env), encoding='utf-8')
        compare('native-cli', native_expected, native_observation)
        apache_root = apache.parent.parent.as_posix()
        apache_config = OUT / 'httpd.conf'
        apache_config.write_text('\n'.join([
            'ServerRoot "' + apache_root + '"', 'Listen ' + native_origin.removeprefix('http://'), 'ServerName 127.0.0.1',
            'AccessFileName .emailing-t16-disabled-htaccess',
            'PidFile "' + (OUT / 'httpd.pid').as_posix() + '"', 'ErrorLog "' + (OUT / 'apache-error.log').as_posix() + '"',
            'LoadModule authz_core_module modules/mod_authz_core.so', 'LoadModule dir_module modules/mod_dir.so',
            'LoadModule mime_module modules/mod_mime.so', 'LoadModule env_module modules/mod_env.so',
            'LoadModule setenvif_module modules/mod_setenvif.so',
            'SetEnvIfNoCase Authorization "(.+)" HTTP_AUTHORIZATION=$1',
            'LoadModule php_module "' + (php_dir / 'php8apache2_4.dll').as_posix() + '"',
            'PHPIniDir "' + php_dir.as_posix() + '"', 'TypesConfig conf/mime.types',
            'DocumentRoot "' + (ROOT / 'public').as_posix() + '"',
            '<Directory "' + (ROOT / 'public').as_posix() + '">', 'Require all granted', 'AllowOverride None', 'DirectoryIndex index.php', 'FallbackResource /index.php', '</Directory>',
            '<FilesMatch "\\.php$">', 'SetHandler application/x-httpd-php', '</FilesMatch>',
            '<FilesMatch "^\\.">', 'Require all denied', '</FilesMatch>',
        ]) + '\n', encoding='utf-8')
        command('apache-config', [str(apache), '-t', '-f', str(apache_config)], env=native_env)
        # -X is one Apache process, so the exact owned Popen handle settles it.
        apache_process = launch('native-apache', [str(apache), '-f', str(apache_config), '-X'], native_env)
        await_health(native_origin, apache_process)
        assert request(native_origin, '/login')[0] == 200
        with zipfile.ZipFile(OUT / 'native-build.zip') as archive:
            for name in archive.namelist():
                if name.endswith('manifest.json'):
                    continue
                assert hashlib.sha256(request(native_origin, '/' + name.removeprefix('public/'))[1]).hexdigest() == hashlib.sha256(archive.read(name)).hexdigest()
        fixture('native-enqueue', 'enqueue', native_owner, native_env)
        ready = fixture('native-before-unsubscribe', 'probe', native_owner, native_env)
        assert ready['queues']['delivery.queue'] == {'ready': 2, 'consumers': 0}
        state_path = ROOT / 'storage/app/private' / ('t16-' + native_owner + '.json')
        state = json.loads(state_path.read_text())
        public_unsubscribe('native-public-unsubscribe', state['unsubscribe_url'])
        after_unsubscribe = fixture('native-after-unsubscribe', 'probe', native_owner, native_env)
        assert after_unsubscribe['queues']['delivery.queue'] == {'ready': 2, 'consumers': 0}
        assert after_unsubscribe['messages'] == [] and relay_messages(native_owner) == []
        command('native-domain-worker', ['php', '-d', 'zend.exception_ignore_args=1', 'artisan', 'deliveries:work', '--max-messages=2'], env=native_env)
        fixture('native-publish-job', 'job', native_owner, native_env)
        command('native-jobs-worker', ['php', '-d', 'zend.exception_ignore_args=1', 'artisan', 'queue:work', 'rabbitmq', '--stop-when-empty', '--tries=1'], env=native_env)
        native_result = fixture('native-result', 'probe', native_owner, native_env)
        assert [x['status'] for x in native_result['deliveries']] == ['suppressed', 'smtp_accepted']
        assert [x['attempts'] for x in native_result['deliveries']] == [0, 1]
        assert native_result['job_completed']
        assert len(relay_messages(native_owner)) == 1
        finite_trigger('native-finite-trigger', native_origin, cronicle_secret)
        override = OUT / 'qa-compose.yaml'
        lines = ['services:']
        for service in ['emailing-php', 'emailing-worker', 'emailing-jobs']:
            lines += ['  ' + service + ':', '    image: ' + PHP_IMAGE, '    env_file: !override [' + json.dumps(str(env_file)) + ']', '    environment:', '      APP_URL: ' + docker_origin]
        lines += ['  emailing-web:', '    image: ' + WEB_IMAGE, '    ports: !override [' + json.dumps(docker_origin.removeprefix('http://') + ':80') + ']']
        override.write_text('\n'.join(lines) + '\n', encoding='utf-8')
        compose = ['docker', 'compose', '--project-name', PROJECT, '-f', 'compose.yaml', '-f', str(override)]
        command('compose-valid', compose + ['config', '--quiet'])
        docker_started = True
        command('docker-http-jobs-up', compose + ['up', '-d', '--no-build', '--wait', '--wait-timeout', '120', 'emailing-php', 'emailing-web', 'emailing-jobs'])
        docker_init_attempted = True
        fixture('docker-init', 'init', docker_owner, native_env, docker=True, password=password)
        docker_initialized = True
        fixture('docker-enqueue', 'enqueue', docker_owner, native_env, docker=True)
        docker_ready = fixture('docker-before-unsubscribe', 'probe', docker_owner, native_env, docker=True)
        assert docker_ready['queues']['delivery.queue'] == {'ready': 2, 'consumers': 0}
        command('docker-copy-access', ['docker', 'cp', PROJECT + '-emailing-php-1:/var/www/emailing/storage/app/private/t16-' + docker_owner + '.json', str(OUT / 'docker-access.private.json')])
        protect('docker-access-acl', OUT / 'docker-access.private.json')
        docker_state = json.loads((OUT / 'docker-access.private.json').read_text())
        public_unsubscribe('docker-public-unsubscribe', docker_state['unsubscribe_url'])
        assert fixture('docker-after-unsubscribe', 'probe', docker_owner, native_env, docker=True)['messages'] == []
        command('docker-domain-up', compose + ['up', '-d', '--no-build', '--wait', '--wait-timeout', '120', 'emailing-worker'])
        fixture('docker-publish-job', 'job', docker_owner, native_env, docker=True)
        deadline = time.monotonic() + 30
        while True:
            docker_result = fixture('docker-progress', 'probe', docker_owner, native_env, docker=True)
            if [x['status'] for x in docker_result['deliveries']] == ['suppressed', 'smtp_accepted'] and docker_result['job_completed']:
                break
            assert time.monotonic() < deadline, 'Owned consumers did not produce durable outcomes.'
            time.sleep(.1)
        assert [x['attempts'] for x in docker_result['deliveries']] == [0, 1]
        assert len(relay_messages(docker_owner)) == 1
        for service in ['emailing-php', 'emailing-worker', 'emailing-jobs']:
            name = PROJECT + '-' + service + '-1'
            projection = json.loads(command(service + '-identity', ['docker', 'inspect', '--format', '{"id":{{json .Id}},"image":{{json .Image}},"state":{{json .State.Status}},"restarts":{{json .RestartCount}},"command":{{json .Config.Cmd}},"mounts":{{json .Mounts}}}', name]))
            assert projection['image'] == (OUT / 'php-image-id.txt').read_text().strip()
            assert projection['state'] == 'running' and projection['restarts'] == 0
            assert not any(m['Type'] == 'bind' for m in projection['mounts'])
            observation = OUT / (service + '-inventory.json')
            observation.write_text(command(service + '-inventory', ['docker', 'exec', name, 'php', '-n', 'tests/support/runtime-source-inventory.php']), encoding='utf-8')
            compare(service, docker_expected, observation)
        command('web-assets', [sys.executable, 'tests/support/web_asset_inventory.py', '--container', PROJECT + '-emailing-web-1', '--origin', docker_origin, '--build', str(OUT / 'docker-build.zip'), '--output', str(OUT / 'web-assets.json')])
        web_projection = json.loads(command('emailing-web-identity', ['docker', 'inspect', '--format', '{"id":{{json .Id}},"image":{{json .Image}},"state":{{json .State.Status}},"restarts":{{json .RestartCount}},"command":{{json .Config.Cmd}},"mounts":{{json .Mounts}}}', PROJECT + '-emailing-web-1']))
        assert web_projection['image'] == (OUT / 'web-image-id.txt').read_text().strip()
        assert web_projection['state'] == 'running' and web_projection['restarts'] == 0
        assert not any(m['Type'] == 'bind' for m in web_projection['mounts'])
        nginx_hash = command('nginx-configuration', ['docker', 'exec', PROJECT + '-emailing-web-1', 'sha256sum', '/etc/nginx/conf.d/default.conf']).split()[0]
        assert nginx_hash == sha(ROOT / 'docker/nginx.conf')
        finite_trigger('docker-finite-trigger', docker_origin, cronicle_secret)
        results = {'native': native_result, 'docker': docker_result, 'manual_m01': 'pending-human', 'native_origin': native_origin, 'docker_origin': docker_origin}
        save('review-surfaces.json', {'origins': {'native': native_origin, 'docker': docker_origin}, 'paths': ['/login', '/campaigns', '/activity', '/suppressions', '/contacts', '/team'], 'roles': ['ownerA (Admin A, Read-only B)', 'ownerB (Admin B)', 'reader (Read-only A)', 'restricted (campaigns.read/activity.read A)', 'foreign (no membership)'], 'manual_m01': 'pending-human', 'private_access_files': [str(state_path), 'docker-access.private.json']})
        save('profile-results.json', results)
        if args.hold:
            save('ready-for-review.json', {'owner': OWN, 'stop_file': 'stop-owned-runtime', 'manual_m01': 'pending-human'})
            while not (OUT / 'stop-owned-runtime').exists():
                time.sleep(.5)
    finally:
        def settle(label, action):
            try:
                action()
            except BaseException as error:
                cleanup_errors.append({'stage': label, 'exception_class': type(error).__name__})
        settle('native-processes', stop_children)
        if docker_started:
            settle('docker-consumers-stop', lambda: command('docker-consumers-stop', compose + ['stop', 'emailing-worker', 'emailing-jobs']))
        native_state = ROOT / 'storage/app/private' / ('t16-' + native_owner + '.json')
        if native_initialized or (native_init_attempted and native_state.is_file()):
            settle('native-fixture', lambda: fixture('native-cleanup', 'cleanup', native_owner, native_env))
        if docker_initialized or docker_init_attempted:
            settle('docker-fixture', lambda: fixture('docker-cleanup', 'cleanup', docker_owner, native_env, docker=True))
        for owner in [native_owner, docker_owner]:
            settle('mailpit-' + owner, lambda owner=owner: save('mailpit-settlement-' + owner + '.json', {'deleted_ids': settle_relay(owner), 'remaining': 0}))
        if docker_started:
            settle('owned-compose', lambda: command('owned-compose-down', compose + ['down', '-v', '--remove-orphans']))
        if artifact_created:
            settle('artifact-container', lambda: command('artifact-remove', ['docker', 'rm', artifact_name]))
        save('settlement.json', {'owner': OWN, 'cleanup_errors': cleanup_errors, 'native_children_terminal': all(p.poll() is not None for _, p, _, _, _ in children), 'profile_results_available': sorted(results)})
    assert not cleanup_errors, 'Owned resources require attributed recovery.'
    assert results, 'Profile proof incomplete.'
    print(json.dumps({'profiles': ['native-apache-php-domain-jobs', 'docker-web-php-domain-jobs'], 'manual_m01': 'pending-human', 'settled': True}))


if __name__ == '__main__':
    main()
