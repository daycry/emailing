"""Delivery development evidence producer. Does not record environment values."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
from datetime import datetime, timezone
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'storage/qa-delivery'
BASE.mkdir(parents=True, exist_ok=True)
label, command = sys.argv[1], sys.argv[2:]
if command and command[0] == '--':
    command.pop(0)
assert label and all(x.isalnum() or x in '-_' for x in label)
assert command
run = BASE / (label + '-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ'))
run.mkdir(parents=True)
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
def source_files():
    result = []
    for directory in ['app', 'config', 'database', 'routes', 'tests', 'scripts', 'docker', 'resources', 'lang', 'bootstrap', 'public']:
        for path in (ROOT / directory).rglob('*'):
            if path.is_symlink(): raise RuntimeError('Linked snapshot source refused.')
            if path.is_file() and not path.name.casefold().startswith('.env') and '.runtime' not in path.parts and '__pycache__' not in path.parts and not (directory == 'bootstrap' and 'cache' in path.parts):
                result.append(path)
    for name in ['artisan', 'composer.json', 'composer.lock', 'compose.yaml', '.dockerignore', 'phpunit.xml', '.env.example', 'package.json', 'package-lock.json', 'vite.config.js', 'infra/compose.yaml', 'infra/compose.emailing-cronicle.yaml', 'docs/aplicacion/programacion-con-cronicle.md']:
        path = ROOT / name
        if path.is_file():
            result.append(path)
    result.append(Path(__file__))
    return sorted(set(result))

files = source_files()
manifest = {p.relative_to(ROOT).as_posix(): sha(p) for p in files}
(run / 'source-manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
with zipfile.ZipFile(run / 'sources.zip', 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(path, path.relative_to(ROOT).as_posix())
helpers = []
for argument in command:
    candidate = ROOT / argument
    if candidate.is_file() and candidate.suffix in ['.py', '.php', '.ps1', '.cjs'] and ROOT in candidate.resolve().parents:
        destination = run / 'command-sources' / candidate.name
        destination.parent.mkdir(exist_ok=True)
        shutil.copyfile(candidate, destination)
        helpers.append({'origin': candidate.relative_to(ROOT).as_posix(), 'copy': destination.relative_to(run).as_posix(), 'sha256': sha(candidate)})
(run / 'command-source-origins.json').write_text(json.dumps(helpers, indent=2), encoding='utf-8')
env = os.environ.copy()
runtime = run / 'php-runtime'
runtime.mkdir()
(runtime / 'runtime.ini').write_text('extension=sockets\nzend.exception_ignore_args=1\n', encoding='ascii')
shutil.copyfile(ROOT / 'docker/emailing-uploads.ini', runtime / 'uploads.ini')
env.update(PHP_INI_SCAN_DIR=str(runtime), XDEBUG_MODE='off', APP_ENV='testing', DB_DATABASE='emailing_testing', DB_USERNAME='emailing_testing', DB_URL='',
           T16_EVIDENCE_DIRECTORY=str(run), T16_EVIDENCE_RUN=run.name,
           T15_EVIDENCE_DIRECTORY=str(run / 't15'), T15_EVIDENCE_RUN=run.name,
           T14_EVIDENCE_DIRECTORY=str(run / 't14'), T14_EVIDENCE_RUN=run.name,
           T13_EVIDENCE_DIRECTORY=str(run / 't13'), T13_EVIDENCE_RUN=run.name,
           T12_EVIDENCE_DIRECTORY=str(run / 't12'), T12_EVIDENCE_RUN=run.name, T12_VENDOR_PROFILE='image', PYTHONUTF8='1')
start = datetime.now(timezone.utc).isoformat()
artifact_roots = [ROOT / 'storage/phase3-t10', ROOT / 'storage/phase3-t13/implementation', ROOT / 'storage/phase3-t15/runtime', ROOT / 'tests/.runtime']
before = {p.relative_to(ROOT).as_posix(): p.stat().st_mtime_ns for root in artifact_roots if root.exists() for p in root.rglob('*') if p.is_file()}
(run / 'artifact-inventory-before.json').write_text(json.dumps(before, indent=2), encoding='utf-8')
(run / 'request.json').write_text(json.dumps({'command': command, 'started_at': start, 'source_manifest_sha256': sha(run / 'source-manifest.json')}, indent=2), encoding='utf-8')
print(json.dumps({'run': str(run), 'command': command}), flush=True)
with (run / 'stdout.txt').open('wb') as out, (run / 'stderr.txt').open('wb') as err:
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=out, stderr=err)
    (run / 'process.json').write_text(json.dumps({'pid': process.pid, 'command': command, 'run': run.name}, indent=2), encoding='utf-8')
    code = process.wait()
copied = []
for root in artifact_roots:
    if not root.exists():
        continue
    for path in root.rglob('*'):
        if not path.is_file() or path.relative_to(ROOT).as_posix() in before:
            continue
        entry = {'origin': path.relative_to(ROOT).as_posix(), 'sha256': sha(path)}
        if not path.name.startswith('.env') and (path.suffix in ['.json', '.txt', '.jsonl', '.out', '.err', '.php', '.py', '.ps1', '.cjs'] or path.name.startswith(('ready', 'start', 'attempting', 'loaded-', 'terminal-', 'stage-'))):
            destination = run / 'attributed-artifacts' / path.relative_to(ROOT)
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, destination)
            entry['copy'] = destination.relative_to(run).as_posix()
        copied.append(entry)
(run / 'artifact-attribution.json').write_text(json.dumps(copied, indent=2), encoding='utf-8')
continuity = {p.relative_to(ROOT).as_posix(): sha(p) for p in source_files()}
changed = sorted(name for name in manifest.keys() | continuity.keys() if manifest.get(name) != continuity.get(name))
receipt = {'command': command, 'started_at': start, 'ended_at': datetime.now(timezone.utc).isoformat(), 'exit_code': code,
           'source_manifest_sha256': sha(run / 'source-manifest.json'), 'source_count': len(manifest), 'source_changed_during_run': changed,
           'stdout_sha256': sha(run / 'stdout.txt'), 'stderr_sha256': sha(run / 'stderr.txt')}
(run / 'receipt.json').write_text(json.dumps(receipt, indent=2), encoding='utf-8')
print(json.dumps({'run': str(run), 'exit_code': code, 'source_changed_during_run': changed}), flush=True)
sys.exit(code or bool(changed))
