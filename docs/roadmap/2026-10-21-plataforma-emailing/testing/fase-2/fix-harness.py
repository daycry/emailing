from pathlib import Path
import json,hashlib,difflib,re
out=Path(__file__).resolve().parent
manifest=json.loads((out/'raw/harness-sha256-attempt-1.json').read_text())
archive=out/'raw/artifacts/attempt-1-source';archive.mkdir(exist_ok=True)
for name,digest in manifest.items():
 source=out/name
 body=(out/'raw/artifacts/cleanup-owned-attempt-1-original.py').read_bytes() if name=='cleanup-owned.py' else source.read_bytes()
 assert hashlib.sha256(body).hexdigest()==digest,name
 dest=archive/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(body)
def edit(name,old,new):
 p=out/name;s=p.read_text(encoding='utf-8');assert old in s,name;p.write_text(s.replace(old,new),encoding='utf-8')
edit('run-gates.py',"Path("+repr((Path.home()/'.codex/plugins/cache/daycry/custom-agents/1.22.0').as_posix())+")","Path.home()/'.codex/plugins/cache/daycry/custom-agents/1.22.0'")
for name in ['prepare.py','specs/helpers.mjs']:
 edit(name,"encoding:'utf8',windowsHide:true,timeout:60000","encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000")
helper=out/'specs/helpers.mjs'
helper.write_text(helper.read_text(encoding='utf-8')+'''\nexport async function clickCall(page,method,action){const wait=page.waitForResponse(r=>{try{return r.request().method()==='POST'&&JSON.parse(r.request().postData()||'{}').components?.some(c=>c.calls?.some(x=>x.method===method));}catch{return false;}});await action();const response=await wait;expect(response.status()).toBe(200);await page.waitForFunction(()=>{const c=window.Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id'));return c!==undefined;});}\n''',encoding='utf-8')
edit('specs/E2E-05.spec.mjs',"\"'=SUM(1)\"","\"'=sum(1)\"")
edit('specs/E2E-05.spec.mjs',"r.url().includes('/livewire/upload-file')","new URL(r.url()).pathname.endsWith('/upload-file')")
edit('specs/E2E-05.spec.mjs',"expect((await lw(op,'import',[],{file})).status).not.toBe(200);","expect([403,404,422]).toContain((await lw(op,'import',[],{file})).status);")
edit('specs/E2E-05.spec.mjs',"const denied=await page.request.post(uploadURL,{multipart:","const csrf=await page.locator('meta[name=csrf-token]').getAttribute('content');const denied=await page.request.post(uploadURL,{headers:{'X-CSRF-TOKEN':csrf},multipart:")
edit('specs/E2E-05.spec.mjs',"expect([401,403,419,422]).toContain(denied.status());","expect(denied.status()).toBe(403);")
edit('specs/E2E-06.spec.mjs',"resources,uiCall}","resources,uiCall,clickCall}")
edit('specs/E2E-06.spec.mjs',"async function create(page,{name,subject='Saved subject',html='<p>Saved HTML</p>',text='Saved TEXT'}){","async function create(page,{name,subject='Saved subject',html='<p>Saved HTML</p>',text='Saved TEXT'},f){")
edit('specs/E2E-06.spec.mjs',"await page.getByRole('button',{name:'Nueva plantilla',exact:true}).click();\n for", "await clickCall(page,'create',()=>page.getByRole('button',{name:'Nueva plantilla',exact:true}).click());\n await expect(field(page,'name')).toHaveValue('');\n for")
edit('specs/E2E-06.spec.mjs',"await page.getByRole('button',{name:'Guardar plantilla',exact:true}).click();\n await expect", "await clickCall(page,'save',()=>page.getByRole('button',{name:'Guardar plantilla',exact:true}).click());\n await expect.poll(()=>resources(f).email_templates.some(t=>t.name===name)).toBe(true);\n await expect")
edit('specs/E2E-06.spec.mjs',"text:''});","text:''},f);")
edit('specs/E2E-06.spec.mjs',"text:'TEXT ONLY BODY'});","text:'TEXT ONLY BODY'},f);")
edit('specs/E2E-07.spec.mjs',"await page.locator('[name=smtp_username]').fill('error-user-", "await page.locator('details').first().evaluate(el=>el.open=true);await page.locator('[name=smtp_username]').fill('error-user-")
edit('specs/E2E-07.spec.mjs',"await page.locator('[name=smtp_host]').fill('relay.invalid');", "await page.locator('details').first().evaluate(el=>el.open=true);await page.locator('[name=smtp_host]').fill('relay.invalid');")
edit('prepare.py',"old=out.parent", "old=out.parent\nif (out/'specs/helpers.mjs').exists():raise SystemExit('Harness already prepared; run run.py with an explicit new attempt number.')")
edit('playwright.config.mjs',"path.join(here,'raw/artifacts/results-attempt-1-original.json')","path.join(here,'raw/artifacts',`results-attempt-${process.env.QA_ATTEMPT||1}-original.json`)")
p=out/'run.py';s=p.read_text(encoding='utf-8').replace('import subprocess,os,json,socket,time,hashlib','import subprocess,os,json,socket,time,hashlib,sys')
s=s.replace("cache=Path.home()", "attempt=int(sys.argv[1]) if len(sys.argv)>1 else 1\nif (out/f'raw/artifacts/results-attempt-{attempt}-original.json').exists():raise SystemExit('Existing attempt cannot be overwritten')\ncache=Path.home()")
s=s.replace("env['REGISTRATION_ENABLED']='false'", "env['REGISTRATION_ENABLED']='false';env['QA_ATTEMPT']=str(attempt)")
s=re.sub(r"'([^'\n]*attempt-1[^'\n]*)'",lambda m:"f'"+m[1].replace('attempt-1','attempt-{attempt}')+"'",s)
s=s.replace("{'exit':p.returncode,'attempt':1}","{'exit':p.returncode,'attempt':attempt}")
start=s.index(' original=json.loads',s.index('finally:'))
s=s[:start]+" stop=capture(['powershell','-NoProfile','-File',str(out/'stop-owned-server.ps1')],f'server-off-teardown-attempt-{attempt}.txt')\n (out/f'raw/server-teardown-attempt-{attempt}.json').write_text(json.dumps({'exit':stop.returncode}))\n serverLog.close();serverErr.close()\n"
p.write_text(s,encoding='utf-8')
diff=[];after={}
for name in manifest:
 before=(archive/name).read_text(encoding='utf-8');current=(out/name).read_text(encoding='utf-8');after[name]=hashlib.sha256((out/name).read_bytes()).hexdigest()
 if before!=current:diff.extend(difflib.unified_diff(before.splitlines(True),current.splitlines(True),fromfile='attempt-1/'+name,tofile='next-attempt/'+name))
patch=''.join(diff)
(out/'raw/artifacts/harness-post-attempt-1-original.patch').write_text(patch,encoding='utf-8')
patch=re.sub(r'(?i)(?:[A-Z]:[/\\]+Users[/\\]+|/Users/|/home/)[^/\\\r\n\t\"\'<> ]+','<USER_PROFILE>',patch)
(out/'raw/harness-post-attempt-1.patch').write_text(patch,encoding='utf-8')
(out/'raw/harness-post-attempt-1-sha256.json').write_text(json.dumps(after,indent=2))
print('Post-suite harness fixes archived; assertions retained or strengthened. No tests rerun.')
