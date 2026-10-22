import fs from 'node:fs';import path from 'node:path';import {homedir} from 'node:os';import {createRequire} from 'node:module';import assert from 'node:assert/strict';
import {fixture,login,upload,out,resource,command,state,cleanup} from './specs/helpers.mjs';import {cleanupTemporaryRun,registryDir,temporaryCommand} from './temporary-uploads.mjs';
const require=createRequire(import.meta.url),{chromium}=require(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});const f=fixture(),contexts=[],receipt={node:process.version,profiles:[]};
try{
 for(const [profile,port] of [['native',8082],['docker',8083]]){
  resource('restore-read',f);const context=await browser.newContext({baseURL:`http://127.0.0.1:${port}`});contexts.push(context);const page=await context.newPage();await login(page,f,'marketer');await page.goto('/contacts/imports');
  await upload(page,`b03-order-${f.run}-${profile}.csv`,Buffer.from(`email,name\nqa-${f.run}@example.test,Unimported ${f.run}\n`),f,'marketer');
  const entry=JSON.parse(fs.readFileSync(path.join(registryDir,f.run+'.json'))).find(e=>e.profile===profile);assert(entry);assert(temporaryCommand(profile,'inspect',f.run,entry).exists.every(Boolean));
  resource('revoke-read',f);const member=state(f).members.find(m=>m.id===f.members.marketer);assert(!member.scopes.includes('contacts.read')&&member.scopes.includes('contacts.write'));
  receipt.profiles.push({profile,actor_read_revoked_after_capture:true,upload_still_unimported:true});
 }
 for(const context of contexts)await context.close();contexts.length=0;
 command('cleanup',f.run);assert(!fs.existsSync(path.join(out,'raw/artifacts',f.run+'.json')));receipt.sql_fixtures_removed_before_temporary_cleanup=true;
 const cleaned=cleanupTemporaryRun(f.run);assert.equal(cleaned.length,2);for(const row of cleaned){assert(row.deleted>=1);assert.equal(row.registered_files_remaining,0);}
 receipt.cleanup=cleaned;console.log(JSON.stringify(receipt));
}finally{
 if(fs.existsSync(path.join(out,'raw/artifacts',f.run+'.json')))await cleanup(f,contexts);else cleanupTemporaryRun(f.run);
 await browser.close();fs.writeFileSync(path.join(out,'raw/b03-fix/teardown-order.json'),JSON.stringify(receipt,null,2));
}
