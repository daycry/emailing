"""Derive expected runtime bytes from frozen source, build and dependency artifacts.

No observation input is accepted. The caller approves each input artifact for the
chosen profile. Native development and Linux production dependencies stay separate.
"""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import stat
import zipfile

SOURCE_TREES = ('app/', 'bootstrap/', 'config/', 'database/migrations/', 'routes/', 'resources/views/', 'lang/')
SOURCE_FILES = {'public/index.php', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js'}


def digest(data):
    return hashlib.sha256(data).hexdigest()


def collect(path, select):
    result = {}
    with zipfile.ZipFile(path) as archive:
        for entry in archive.infolist():
            if entry.is_dir() or not select(entry.filename):
                continue
            name = entry.filename
            if (PurePosixPath(name).is_absolute() or '\\' in name or ':' in name
                    or any(part in ('', '.', '..') or part.casefold().startswith('.env') for part in name.split('/'))
                    or stat.S_ISLNK(entry.external_attr >> 16)):
                raise ValueError('unsafe_artifact_member')
            if name in result:
                raise ValueError('duplicate_artifact_member')
            result[name] = digest(archive.read(entry))
    if not result:
        raise ValueError('empty_artifact_scope')
    return result


def produce(source, build, dependencies, php_version, platform, reporter):
    files = collect(source, lambda name: (name in SOURCE_FILES or name.startswith(SOURCE_TREES)) and not name.startswith('bootstrap/cache/'))
    files.update(collect(build, lambda name: name.startswith('public/build/')))
    files.update(collect(dependencies, lambda name: name.startswith(('vendor/', 'bootstrap/cache/'))))
    return {'schema_version': 1, 'kind': 'accepted-runtime-source-manifest', 'php_version': php_version,
            'platform': platform, 'reporter_sha256': digest(Path(reporter).read_bytes()), 'files': dict(sorted(files.items())),
            'artifact_sha256': {'source': digest(Path(source).read_bytes()), 'build': digest(Path(build).read_bytes()),
                                'dependencies': digest(Path(dependencies).read_bytes())}}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for argument in ('source', 'build', 'dependencies', 'php-version', 'platform', 'reporter', 'output'):
        parser.add_argument('--' + argument, required=True)
    args = parser.parse_args()
    try:
        result = produce(args.source, args.build, args.dependencies, args.php_version, args.platform, args.reporter)
        # Reuse the strict contract before publishing an expected artifact.
        from runtime_inventory_compare import validate
        validate(result, 'accepted-runtime-source-manifest')
        Path(args.output).write_text(json.dumps(result, sort_keys=True), encoding='utf-8')
    except (OSError, ValueError, zipfile.BadZipFile):
        print(json.dumps({'error': 'qa_expected_artifact_failed'}))
        return 2
    print(json.dumps({'expected_files': len(result['files']), 'artifact_sha256': result['artifact_sha256']}))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
