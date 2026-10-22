from pathlib import Path
import subprocess,json,hashlib
out=Path(__file__).resolve().parent;root=out.parents[4]
raw=out/'raw/artifacts/b03-fix';raw.mkdir(parents=True,exist_ok=True)
if (raw/'red.stdout.txt').exists():raise SystemExit('Existing B03 RED evidence cannot be overwritten')
sources=[p for p in out.rglob('*') if p.is_file() and 'raw' not in p.relative_to(out).parts and p.suffix in ['.py','.php','.mjs','.ps1'] and 'review' not in p.parts]
(raw/'before-hashes.json').write_text(json.dumps({str(p.relative_to(out)):hashlib.sha256(p.read_bytes()).hexdigest() for p in sources},indent=2))
with (raw/'red.stdout.txt').open('wb') as log:p=subprocess.run(['node',str(out/'b03-probe.mjs')],cwd=root,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
(raw/'red-exit.json').write_text(json.dumps({'exit':p.returncode}))
print('B03 RED process exit',p.returncode)
