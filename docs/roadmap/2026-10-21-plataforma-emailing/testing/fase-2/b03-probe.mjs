import fs from 'node:fs';import path from 'node:path';import {homedir} from 'node:os';import {createRequire} from 'node:module';import assert from 'node:assert/strict';
import {fixture,login,upload,out} from './specs/helpers.mjs';
import {temporaryCommand,recordTemporaryUpload} from './temporary-uploads.mjs';
const require=createRequire(import.meta.url),{chromium}=require(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const f=fixture(),receipt={run:f.run,profiles:[],expected_red:true};let failures=0;
try{
 for(const [profile,port] of [['native',8082],['docker',8083]]){
  const context=await browser.newContext({baseURL:`http://127.0.0.1:${port}`});
  try{
   const page=await context.newPage();await login(page,f);await page.goto('/contacts/imports');
   const name=`b03-${f.run}-${profile}.csv`,buffer=Buffer.from(`email,name\nqa-${f.run}@example.test,Unimported ${f.run}\n`);
   await upload(page,name,buffer,f);const entry=await recordTemporaryUpload(page,f,name,buffer);
   const before=temporaryCommand(profile,'inspect',f.run,entry),after=temporaryCommand(profile,'legacy-clean',f.run,entry);
   assert.equal(before.imports,0);assert(before.exists.every(Boolean));
   let red=false;try{assert(after.exists.every(x=>!x),'Old cleanup must remove own unimported upload');}catch(error){red=true;failures++;console.log(profile+' RED: '+error.message);}
   receipt.profiles.push({profile,configured_disk:before.configured_disk,configured_directory:before.configured_directory,legacy_directory_differs:after.legacy_directory_differs,upload_exists_after_old_cleanup:after.exists.some(Boolean),imports:after.imports,red});
  }finally{await context.close();}
 }
}finally{await browser.close();fs.mkdirSync(path.join(out,'raw/b03-fix'),{recursive:true});fs.writeFileSync(path.join(out,'raw/b03-fix/red.json'),JSON.stringify(receipt,null,2));}
console.log(JSON.stringify({red_failures:failures,profiles:receipt.profiles}));process.exitCode=failures===2?1:2;
