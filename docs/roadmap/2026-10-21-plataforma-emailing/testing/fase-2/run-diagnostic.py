from pathlib import Path
import subprocess,json
out=Path(__file__).resolve().parent
with (out/'raw/artifacts/diagnostic-attempt-1.stdout.txt').open('wb') as log:
 p=subprocess.run(['node',str(out/'diagnostic.mjs')],cwd=out.parents[4],stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
(out/'raw/diagnostic-exit.json').write_text(json.dumps({'exit':p.returncode}))
print('Diagnostic exit',p.returncode)
