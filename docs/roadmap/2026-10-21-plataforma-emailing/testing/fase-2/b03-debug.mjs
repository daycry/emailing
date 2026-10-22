import fs from 'node:fs';import path from 'node:path';import {homedir} from 'node:os';import {createRequire} from 'node:module';
import {fixture,login,cleanup,out} from './specs/helpers.mjs';import {trackedUpload,recordTemporaryUpload,registryDir,temporaryCommand} from './temporary-uploads.mjs';
const require=createRequire(import.meta.url),{chromium}=require(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});const f=fixture(),contexts=[],receipt={run:f.run};
try{
 const context=await browser.newContext({baseURL:'http://127.0.0.1:8082'});contexts.push(context);const page=await context.newPage();await login(page,f);await page.goto('/contacts/imports');
 const name=`b03-debug-${f.run}.csv`,buffer=Buffer.from(`email,name\nqa-${f.run}@example.test,Unimported ${f.run}\n`);
 try{await trackedUpload(page,f,name,buffer);receipt.response_record_succeeded=true;}catch(error){receipt.response_record_succeeded=false;receipt.error=error.message;}
 await page.waitForFunction(()=>!!window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).$get('file'));
 const componentHandle=await page.evaluate(()=>window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).$get('file'));
 const files=fs.readdirSync(path.join(registryDir,'commands')).filter(n=>n.startsWith(f.run)&&n.includes('-record-')&&n.endsWith('.input.json'));
 const captured=JSON.parse(fs.readFileSync(path.join(registryDir,'commands',files[0])));
 receipt.response_decoded=temporaryCommand('native','decode-observe',f.run,{handle:captured.payload.handle});receipt.component_decoded=temporaryCommand('native','decode-observe',f.run,{handle:componentHandle});
 const entry=await recordTemporaryUpload(page,f,name,buffer);receipt.component_record_succeeded=!!entry.proof;
 console.log(JSON.stringify({response_record_succeeded:receipt.response_record_succeeded,component_record_succeeded:receipt.component_record_succeeded,response_decoded:receipt.response_decoded,component_decoded:receipt.component_decoded}));
}finally{fs.writeFileSync(path.join(out,'raw/artifacts/b03-fix/debug-handles.json'),JSON.stringify(receipt,null,2));await cleanup(f,contexts);await browser.close();}
