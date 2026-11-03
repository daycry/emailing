"""Closed source inventory: private configuration, runtime state and archives are excluded."""
from pathlib import Path,PurePosixPath
import hashlib
import subprocess

ROOT=Path(__file__).resolve().parents[2]

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def sources():
    names = set(filter(None, subprocess.check_output(['git','ls-files','-z'],cwd=ROOT).decode().split('\0')))
    names.update(filter(None,subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z'],cwd=ROOT).decode().split('\0')))
    roots = {'app','bootstrap','config','database','routes','resources','tests','scripts','docker','lang','public'}
    singles = {'composer.json','composer.lock','package.json','package-lock.json','phpunit.xml','compose.yaml','compose.tls.yaml','.env.example','docs/api/mcp-tools.json','docs/api/mcp-oauth.openapi.json','docs/api/openapi.yaml','docs/api/mail-provider-webhooks.openapi.json','.gitattributes','.gitignore','.dockerignore','.htaccess','artisan','vite.config.js','playwright.config.mjs'}
    result = {}
    for name in sorted(names):
        p = PurePosixPath(name)
        if p.parts[0] not in roots and name not in singles:
            continue
        low = p.name.lower()
        if (name.startswith(('tests/.runtime/','bootstrap/cache/')) and low != '.gitignore'
            or low in ('request.json','owner.json','process.json','settlement.json','qa.private.env','fixtures.private.json')
            or low.endswith(('.env','-request.json','.pem','.key','.crt','.cer','.p12','.pfx','.zip','.tar','.gz')) or low.startswith('.env') and low != '.env.example'):
            continue
        path = ROOT / name
        current = ROOT
        for part in p.parts:
            current = current / part
            if current.is_symlink() or current.is_junction():
                raise RuntimeError('source_path_link_refused')
        if path.is_file():
            if not path.resolve().is_relative_to(ROOT.resolve()) or path.stat().st_nlink != 1:
                raise RuntimeError('source_path_link_refused')
            assert path.stat().st_size <= 32*1048576
            result[name] = digest(path)
    return result
