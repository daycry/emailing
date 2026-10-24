"""Attest Nginx-served files and HTTP bytes separately from PHP inventories.

Read only. Only a caller-specified owned container is inspected. No environment,
Docker configuration, credentials, authenticated request or private file is read.
"""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import urllib.request
import zipfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--container', required=True)
    parser.add_argument('--origin', required=True)
    parser.add_argument('--build', required=True)
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    command = ['docker', 'exec', args.container, 'sh', '-c', 'cd /var/www/emailing/public && find build -type f -exec sha256sum {} \\;']
    process = subprocess.run(command, capture_output=True)
    if process.returncode or process.stderr:
        raise RuntimeError('Owned web inventory unavailable.')
    observed = {}
    for line in process.stdout.decode('utf-8').splitlines():
        match = re.fullmatch(r'([0-9a-f]{64})  (build/[a-zA-Z0-9_./-]+)', line)
        if not match or '..' in match[2].split('/') or match[2] in observed:
            raise RuntimeError('Invalid owned web inventory.')
        observed[match[2]] = match[1]
    expected = {}
    with zipfile.ZipFile(args.build) as archive:
        for name in archive.namelist():
            if name.startswith('public/build/') and not name.endswith('/'):
                expected[name.removeprefix('public/')] = hashlib.sha256(archive.read(name)).hexdigest()
    if not expected or observed != expected:
        raise RuntimeError('Owned web source differs from build artifact.')
    served = {}
    for name in expected:
        if name == 'build/manifest.json':
            continue
        with urllib.request.urlopen(args.origin.rstrip('/') + '/' + name, timeout=10) as response:
            served[name] = hashlib.sha256(response.read()).hexdigest()
    if served != {name: value for name, value in expected.items() if name != 'build/manifest.json'}:
        raise RuntimeError('Served web assets differ from build artifact.')
    result = {'kind': 'web-assets-comparison', 'passed': True, 'container': args.container,
              'build_artifact_sha256': hashlib.sha256(Path(args.build).read_bytes()).hexdigest(),
              'visible_files': observed, 'served_files': served, 'command': command,
              'stdout_sha256': hashlib.sha256(process.stdout).hexdigest(), 'stderr_sha256': hashlib.sha256(process.stderr).hexdigest()}
    Path(args.output).write_text(json.dumps(result, sort_keys=True), encoding='utf-8')
    print(json.dumps({'passed': True, 'visible_asset_count': len(observed), 'served_asset_count': len(served)}))


if __name__ == '__main__':
    main()
