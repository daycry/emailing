"""Adopt the complete regression gate and verify only public image content."""
import base64
import importlib.util
import json
from pathlib import PurePosixPath
import re
import sys

from contract import require
from worker_io import ROOT

_MODULE = None


def owning_gate():
    global _MODULE
    if _MODULE is None:
        sys.path.insert(0, str(ROOT / 'scripts/broker_lab'))
        spec = importlib.util.spec_from_file_location('_browser_whole_regression_gate', ROOT / 'scripts/broker_lab/load.py')
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        require(module.ROOT == ROOT and module.runtime_hashes.__globals__['ROOT'] == ROOT, 'browser_gate_checkout_mismatch')
        _MODULE = module
    return _MODULE


def sources():
    return owning_gate().runtime_hashes()


def prerequisite(directory):
    return owning_gate().prerequisite(directory)


def app_sources(manifest):
    roots = {'app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'lang'}
    singles = {'composer.json', 'composer.lock', 'artisan'}
    result = {}
    require(isinstance(manifest, dict) and bool(manifest), 'browser_sources_required')
    for name, digest in manifest.items():
        path = PurePosixPath(name)
        require(not path.is_absolute() and path.as_posix() == name and '..' not in path.parts
                and re.fullmatch('[a-zA-Z0-9_./-]+', name) is not None
                and isinstance(digest, str) and re.fullmatch('[0-9a-f]{64}', digest) is not None, 'browser_source_path_refused')
        if path.parts[0] in roots or name in singles:
            leaf = path.name.lower()
            require(not leaf.startswith('.env') and not leaf.endswith(('.env', '.key', '.pem', '.crt'))
                    and (not name.startswith('bootstrap/cache/') or name == 'bootstrap/cache/.gitignore'), 'browser_private_source_refused')
            result[name] = digest
    require(singles <= set(result) and any(name.startswith('app/') for name in result), 'browser_application_sources_missing')
    return result


HASH = ('$names=json_decode(base64_decode($argv[1],true),true,512,JSON_THROW_ON_ERROR);$out=[];'
        'foreach($names as $name){$p=getcwd()."/".$name;'
        'if(is_link($p)||!is_file($p)||realpath($p)!==$p||(stat($p)["nlink"]??0)!==1){exit(2);}'
        '$out[$name]=hash_file("sha256",$p);}echo json_encode($out,JSON_THROW_ON_ERROR);')
ASSETS = ('$m="public/build/manifest.json";if(is_link($m)||realpath($m)!==getcwd()."/".$m||(stat($m)["nlink"]??0)!==1||filesize($m)>262144){exit(2);}'
          '$v=json_decode(file_get_contents($m),true,512,JSON_THROW_ON_ERROR);'
          'echo json_encode($v,JSON_THROW_ON_ERROR);')


def php_hashes(profile, names):
    out = {}
    for offset in range(0, len(names), 32):
        batch = names[offset:offset + 32]
        encoded = base64.b64encode(json.dumps(batch).encode('ascii')).decode('ascii')
        raw = profile.exec_php('hash-public-image-files', ['-n', '-r', HASH, '--', encoded]).stdout
        require(len(raw) <= 32768, 'browser_image_hash_output_limit')
        value = json.loads(raw)
        require(isinstance(value, dict) and set(value) == set(batch)
                and all(isinstance(digest, str) and re.fullmatch('[0-9a-f]{64}', digest) for digest in value.values()),
                'browser_image_hash_result_invalid')
        out.update(value)
    return out


def verify_application(profile, manifest):
    expected = app_sources(manifest)
    require(php_hashes(profile, sorted(expected)) == expected, 'browser_image_application_differs')
    profile.io.save('application-provenance.public.json', {'public_application_files': len(expected),
        'before_fixture_bootstrap': True, 'configuration_read': False})
    return len(expected)


def verify_images(profile, manifest):
    count = verify_application(profile, manifest)
    value = json.loads(profile.exec_php('read-public-asset-manifest', ['-n', '-r', ASSETS], limit=262144).stdout)
    require(isinstance(value, dict) and 0 < len(value) <= 512, 'browser_asset_manifest_invalid')
    names = {'public/build/manifest.json'}
    for entry in value.values():
        require(isinstance(entry, dict) and isinstance(entry.get('file'), str), 'browser_asset_manifest_invalid')
        paths = [entry['file']]
        for kind in ('css', 'assets'):
            require(isinstance(entry.get(kind, []), list), 'browser_asset_manifest_invalid')
            paths += entry.get(kind, [])
        for name in paths:
            require(isinstance(name, str), 'browser_asset_path_refused')
            path = PurePosixPath(name)
            require(name.startswith('assets/') and path.as_posix() == name and '..' not in path.parts
                    and re.fullmatch('[a-zA-Z0-9_./-]+', name) is not None
                    and path.suffix.lower() in ('.js', '.css', '.woff', '.woff2', '.ttf', '.svg', '.png', '.jpg', '.webp', '.avif', '.ico'),
                    'browser_asset_path_refused')
            names.add('public/build/' + name)
    require(len(names) <= 512, 'browser_asset_limit')
    expected_assets = php_hashes(profile, sorted(names))
    script = 'for p do test ! -L "$p" && test "$(readlink -f "$p")" = "$p" && test "$(stat -c %h "$p")" = 1 || exit 2; done; exec sha256sum "$@"'
    found = {}
    paths = sorted(names)
    for offset in range(0, len(paths), 32):
        batch = paths[offset:offset + 32]
        raw = profile.exec_service('web', 'hash-public-web-assets', ['sh', '-ec', script, '--',
            *['/var/www/emailing/' + name for name in batch]]).stdout
        require(len(raw) <= 32768, 'browser_asset_hash_output_limit')
        for line in raw.decode('ascii').splitlines():
            match = re.fullmatch(r'([0-9a-f]{64})  /var/www/emailing/(.+)', line)
            require(match is not None and match[2] in batch and match[2] not in found, 'browser_asset_hash_invalid')
            found[match[2]] = match[1]
    require(found == expected_assets, 'browser_web_assets_differ')
    result = {'public_application_files': count, 'matching_app_web_asset_files': len(names),
              'private_configuration_read': False, 'profile_accepted': False}
    profile.io.save('image-provenance.public.json', result)
    return result
