from pathlib import Path
import subprocess,json,sys,os
out=Path(__file__).resolve().parent;root=out.parents[4];raw=out/'raw/artifacts/b03-fix'
attempt=int(sys.argv[1]) if len(sys.argv)>1 else 2
logfile=raw/f'green-attempt-{attempt}.stdout.txt'
if logfile.exists():raise SystemExit('Existing B03 GREEN evidence cannot be overwritten')
env=os.environ.copy();env['B03_GREEN_ATTEMPT']=str(attempt)
with logfile.open('wb') as log:p=subprocess.run(['C:/laragon/bin/nodejs/node-v22/node.exe',str(out/'b03-green.mjs')],cwd=root,env=env,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
(raw/f'green-attempt-{attempt}-exit.json').write_text(json.dumps({'exit':p.returncode}))
print('B03 GREEN process exit',p.returncode)
