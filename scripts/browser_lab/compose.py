"""Render a fresh browser-only Docker profile, without reading host configuration."""
from pathlib import Path

from contract import SERVICES, VOLUMES, image_id, require

ROOT = Path(__file__).resolve().parents[2]


def compose(plan, images, directory):
    require(isinstance(images, dict) and set(images) == set(SERVICES), 'browser_three_images_required')
    for value in images.values():
        image_id(value)
    directory = Path(directory)
    expected = ROOT / 'storage/mcp-google-docker' / plan.run
    require(directory.is_absolute() and directory == expected and directory.resolve() == expected
            and not any(char in str(directory) for char in ('\0', '\r', '\n', ',')), 'browser_directory_refused')
    mounts = [{'type': 'volume', 'source': name, 'target': target} for name, target in
              [('app-storage', '/var/www/emailing/storage'), ('app-fpm', '/run/emailing-fpm')]]
    for name in ('google-browser-server.php', 'GoogleBrowserBoundary.php', 'GoogleBrowserLease.php', 'google-browser-fixtures.php'):
        mounts.append({'type': 'bind', 'source': str(ROOT / 'tests/support' / name),
            'target': '/var/www/emailing/tests/support/' + name, 'read_only': True,
            'bind': {'create_host_path': False}})
    common = {'labels': plan.labels, 'networks': ['isolated'], 'restart': 'no', 'pull_policy': 'never',
              'logging': {'driver': 'json-file', 'options': {'max-size': '5m', 'max-file': '2'}}}
    environment = {'APP_ENV': 'testing', 'APP_DEBUG': 'false', 'APP_URL': 'http://127.0.0.1:18967',
        'DB_CONNECTION': 'mysql', 'DB_HOST': 'mysql', 'DB_PORT': '3306', 'DB_DATABASE': 'emailing_testing',
        'DB_USERNAME': 'emailing_testing', 'DB_URL': '', 'GOOGLE_BROWSER_TEST': '1',
        'GOOGLE_BROWSER_PROFILE': 'docker', 'GOOGLE_BROWSER_RUN': plan.run,
        'GOOGLE_BROWSER_DIRECTORY': '/var/www/emailing/storage/mcp-google-phase3/browser-' + plan.run,
        'MAIL_MAILER': 'log', 'EMAIL_EXTERNAL_ENABLED': 'false', 'LOG_CHANNEL': 'stderr', 'CACHE_STORE': 'array'}
    services = {
        'mysql': {**common, 'image': images['mysql'], 'container_name': plan.container('mysql'),
            'env_file': [str(directory / 'mysql.private.env')],
            'environment': {'MYSQL_DATABASE': 'emailing_testing', 'MYSQL_USER': 'emailing_testing'},
            'mem_limit': '1g', 'cpus': 1.0,
            'volumes': [{'type': 'volume', 'source': 'mysql-data', 'target': '/var/lib/mysql'}],
            'healthcheck': {'test': ['CMD-SHELL', 'MYSQL_PWD="$$MYSQL_PASSWORD" mysql -u"$$MYSQL_USER" -Nse "SELECT 1" >/dev/null'],
                'interval': '5s', 'timeout': '5s', 'retries': 36, 'start_period': '60s'}},
        'php': {**common, 'image': images['php'], 'container_name': plan.container('php'), 'user': '33:33',
            'env_file': [str(directory / 'application.private.env')], 'environment': environment,
            'entrypoint': ['php-fpm', '-F'], 'command': [], 'mem_limit': '256m', 'cpus': 1.0,
            'volumes': mounts,
            'healthcheck': {'test': ['CMD', 'php', '-r', "exit(@stream_socket_client('unix:///run/emailing-fpm/php.sock') ? 0 : 1);"],
                'interval': '5s', 'timeout': '5s', 'retries': 24}},
        'web': {**common, 'image': images['web'], 'container_name': plan.container('web'),
            'mem_limit': '128m', 'cpus': 0.5, 'ports': ['127.0.0.1:18967:80'],
            'volumes': [{'type': 'volume', 'source': 'app-fpm', 'target': '/run/emailing-fpm', 'read_only': True},
                {'type': 'bind', 'source': str(ROOT / 'docker/nginx-google-browser.conf'),
                 'target': '/etc/nginx/conf.d/default.conf', 'read_only': True, 'bind': {'create_host_path': False}}],
            'depends_on': {'php': {'condition': 'service_healthy'}}},
    }
    # SQL and front-controller volumes are new, labelled and local. A future
    # executor must acquire absence before Compose can create any of them.
    return {'name': plan.project, 'services': services,
        'networks': {'isolated': {'name': plan.project + '-net', 'internal': True, 'labels': plan.labels}},
        'volumes': {name: {'name': plan.volume(name), 'labels': plan.labels} for name in VOLUMES}}
