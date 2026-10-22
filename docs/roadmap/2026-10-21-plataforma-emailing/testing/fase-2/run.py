from pathlib import Path
import subprocess,os,json,socket,time,hashlib,sys
out=Path(__file__).resolve().parent
root=out.parents[4]
attempt=int(sys.argv[1]) if len(sys.argv)>1 else 1
if (out/f'raw/artifacts/results-attempt-{attempt}-original.json').exists():raise SystemExit('Existing attempt cannot be overwritten')
cache=Path.home()/'.claude/tool-cache/qa'
raw=out/'raw/artifacts'
env=os.environ.copy();env['PHP_INI_SCAN_DIR']=str(root/'storage/php-conf');env['REGISTRATION_ENABLED']='false';env['QA_ATTEMPT']=str(attempt)
def capture(args,name,**kwargs):
 with (raw/name).open('wb') as log:return subprocess.run(args,cwd=root,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW,**kwargs)
for name in ['fixture.php','resources.php']:
 p=capture(['php','-l',str(out/name)],name+'.lint.txt',env=env)
 if p.returncode:raise SystemExit('PHP harness lint failed')
for path in (out/'specs').glob('*.mjs'):
 if capture(['node','--check',str(path)],path.name+'.lint.txt').returncode:raise SystemExit('Node harness lint failed')
manifest={str(p.relative_to(out)):hashlib.sha256(p.read_bytes()).hexdigest() for p in [*out.glob('*.php'),*out.glob('*.py'),*out.glob('*.mjs'),*out.glob('specs/*.mjs')]}
(out/f'raw/harness-sha256-attempt-{attempt}.json').write_text(json.dumps(manifest,indent=2))
with socket.socket() as s:s.bind(('127.0.0.1',8084))
serverLog=(raw/'server-off.stdout.txt').open('wb');serverErr=(raw/'server-off.stderr.txt').open('wb')
server=subprocess.Popen(['php',str(out.parent/'server-off.php')],cwd=root,env=env,stdout=serverLog,stderr=serverErr,creationflags=subprocess.CREATE_NO_WINDOW)
script=f"Get-CimInstance Win32_Process -Filter 'ProcessId = {server.pid}' | Select-Object ProcessId,ParentProcessId,CreationDate,ExecutablePath,CommandLine | ConvertTo-Json -Compress"
capture(['powershell','-NoProfile','-Command',script],'server-off-process.json')
try:
 for _ in range(30):
  if server.poll() is not None:raise RuntimeError('Owned registration-off server exited')
  try:
   with socket.create_connection(('127.0.0.1',8084),timeout=.5):break
  except OSError:time.sleep(.5)
 else:raise RuntimeError('Owned registration-off server unavailable')
 print('QA Playwright attempt 1 started, server-off PID '+str(server.pid),flush=True)
 command=['node',str(cache/'node_modules/@playwright/test/cli.js'),'test','-c',str(out/'playwright.config.mjs')]
 p=capture(command,f'playwright-attempt-{attempt}.stdout.txt',env=env)
 (out/f'raw/playwright-attempt-{attempt}-exit.json').write_text(json.dumps({'exit':p.returncode,'attempt':attempt}))
 print('Playwright attempt 1 exit '+str(p.returncode),flush=True)
finally:
 stop=capture(['powershell','-NoProfile','-File',str(out/'stop-owned-server.ps1')],f'server-off-teardown-attempt-{attempt}.txt')
 (out/f'raw/server-teardown-attempt-{attempt}.json').write_text(json.dumps({'exit':stop.returncode}))
 serverLog.close();serverErr.close()
