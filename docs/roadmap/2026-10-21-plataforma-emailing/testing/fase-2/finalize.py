from pathlib import Path
import subprocess,json,os,urllib.request,re
out=Path(__file__).resolve().parent;root=out.parents[4]
raw=out/'raw/artifacts';receipt={'fixtures':[]}
env=os.environ.copy();env['PHP_INI_SCAN_DIR']=str(root/'storage/php-conf')
def capture(args,name):
 with (raw/name).open('wb') as f:return subprocess.run(args,cwd=root,env=env,stdout=f,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
for f in raw.glob('*.json'):
 if not re.fullmatch(r'[a-f0-9-]{36}',f.stem):continue
 run=f.stem
 for args in [['python',str(out/'cleanup-owned.py'),run],['php',str(out/'resources.php'),'precleanup',run],['php',str(out/'fixture.php'),'cleanup',run]]:
  p=capture(args,run+'-final-'+str(len(receipt['fixtures']))+'.txt')
  receipt['fixtures'].append({'run':run,'step':args[0]+':'+args[-2],'exit':p.returncode})
  if p.returncode:raise SystemExit('Owned fixture cleanup failed')
p=capture(['powershell','-NoProfile','-File',str(out/'stop-owned-server.ps1')],'server-off-final-teardown.txt')
receipt['owned_server_teardown_exit']=p.returncode
for port in [8082,8083]:
 receipt[str(port)+'_login_http']=urllib.request.urlopen(f'http://127.0.0.1:{port}/login').status
(out/'raw/teardown-attempt-1.json').write_text(json.dumps(receipt,indent=2))
print(json.dumps(receipt))
