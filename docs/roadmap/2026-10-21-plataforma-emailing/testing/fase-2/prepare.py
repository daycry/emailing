from pathlib import Path
import re
out=Path(__file__).resolve().parent
old=out.parent
if (out/'specs/helpers.mjs').exists():raise SystemExit('Harness already prepared; run run.py with an explicit new attempt number.')
(out/'specs').mkdir(exist_ok=True)
for source in (old/'specs').glob('E2E-0[1-4].spec.mjs'):
 (out/'specs'/source.name).write_bytes(source.read_bytes())
helper=(old/'specs/helpers.mjs').read_text(encoding='utf-8')
helper=helper.replace("'../../../..'","'../../../../..'").replace("'fase-1/screenshots'","'screenshots'")
helper=helper.replace("command('setup',run);", "command('setup',run);resource('setup',{run});")
helper=helper.replace("command('cleanup',f.run);", "execFileSync('python',[path.join(out,'cleanup-owned.py'),f.run],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000});resource('precleanup',f);command('cleanup',f.run);")
helper+='''\nexport function resource(action,f,extra=[]){return execFileSync('php',[path.join(out,'resources.php'),action,f.run,...extra],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000,env:{...process.env,PHP_INI_SCAN_DIR:path.join(root,'storage/php-conf')}}).trim();}\nexport function resources(f){return JSON.parse(resource('status',f));}\nexport async function uiCall(page,method,params=[],updates={}){await settle(page);return page.evaluate(async ({method,params,updates})=>{const c=window.Livewire.find(document.querySelector('[wire\\\\:id]').getAttribute('wire:id'));for(const [k,v] of Object.entries(updates))c.$set(k,v,false);return await c.$call(method,...params);},{method,params,updates});}\nexport async function upload(page,name,buffer){await page.locator('input[type=file]').setInputFiles({name,mimeType:'text/csv',buffer});await expect.poll(()=>page.evaluate(()=>{const el=document.querySelector('[wire\\\\:id]');const c=window.Livewire.find(el.getAttribute('wire:id'));return !!c.$get('file');}),{timeout:45000}).toBe(true);}\n'''
(out/'specs/helpers.mjs').write_text(helper,encoding='utf-8')
fixture=(old/'fixture.php').read_text(encoding='utf-8').replace('dirname(__DIR__, 4)','dirname(__DIR__, 5)')
(out/'fixture.php').write_text(fixture,encoding='utf-8')
config=(old/'playwright.config.mjs').read_text(encoding='utf-8').replace("'fase-1/raw/results.json'","'raw/artifacts/results-attempt-1-original.json'")
(out/'playwright.config.mjs').write_text(config,encoding='utf-8')
print('Prepared phase-2 fixtures and regression specs; original files unchanged.')
