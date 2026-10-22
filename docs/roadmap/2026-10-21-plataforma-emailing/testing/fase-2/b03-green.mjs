import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {randomUUID} from 'node:crypto';
import {out,cleanup,fixture,login,upload} from './specs/helpers.mjs';
import {registryDir,temporaryCommand} from './temporary-uploads.mjs';
import {nativeJunctionProbe} from './b03-junction-probe.mjs';
import {homedir} from 'node:os';import {createRequire} from 'node:module';
const require=createRequire(import.meta.url),{chromium}=require(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const attempt=process.env.B03_GREEN_ATTEMPT||'2',f=fixture(),run=f.run,contexts=[];
const result={node:process.version,profiles:[],history_policy:'Existing files without captured ownership handles are preserved; historical zero-residue statement is not supported'};
try{
 for(const [profile,port] of [['native',8082],['docker',8083]]){
  const context=await browser.newContext({baseURL:`http://127.0.0.1:${port}`});contexts.push(context);const page=await context.newPage();await login(page,f);await page.goto('/contacts/imports');
  await upload(page,`b03-green-${run}-${profile}.csv`,Buffer.from(`email,name\nqa-${run}@example.test,Unimported ${run}\n`),f);
  const entry=JSON.parse(fs.readFileSync(path.join(registryDir,run+'.json'))).find(e=>e.profile===profile);
  assert(entry);const before=temporaryCommand(profile,'inspect',run,entry);assert(before.exists.every(Boolean));assert.equal(before.imports,0);
  assert(entry.captured_response_handle);assert.notEqual(entry.handle,entry.captured_response_handle);
  const sentinel=temporaryCommand(profile,'sentinel-create',run,{id:randomUUID()});
  fs.writeFileSync(path.join(out,'raw/artifacts/b03-fix',profile+'-sentinel-attempt-'+attempt+'.json'),JSON.stringify(sentinel));
  try{
   const guards=temporaryCommand(profile,'guard-probe',run,entry);
   if(profile==='native'){const native=nativeJunctionProbe(entry);guards.outside_symlink=native.php_guard_rejected;guards.outside_link_type='directory_junction';guards.junction_target_preserved=native.target_preserved;}
   assert.equal(guards.traversal,true);assert.equal(guards.foreign_account,true);assert.equal(guards.foreign_user,true);assert.equal(guards.foreign_run,true);assert.equal(guards.own_upload_preserved,true);
   assert.equal(guards.outside_symlink,true,'Actual outside symlink rejection required');
   const cleaned=temporaryCommand(profile,'cleanup',run,entry);assert(cleaned.deleted>=1);assert.equal(cleaned.registered_files_remaining,0);
   const preserved=temporaryCommand(profile,'sentinel-inspect',run,sentinel);assert.equal(preserved.controlled_sentinel_preserved,true);
   const after=temporaryCommand(profile,'inspect',run,entry);assert(after.exists.every(x=>!x));
   result.profiles.push({profile,disk:before.configured_disk,directory:before.configured_directory,unimported:true,registered_files_deleted:cleaned.deleted,registered_files_remaining:cleaned.registered_files_remaining,controlled_foreign_preserved:true,guards});
   console.log(profile+' GREEN: own exact upload removed; controlled foreign file preserved; guards rejected traversal/foreign identity/outside symlink');
  }finally{assert.equal(temporaryCommand(profile,'sentinel-remove',run,sentinel).controlled_sentinel_removed,true);}
 }
}finally{
 await cleanup(f,contexts);await browser.close();
 result.unattributed_preserved=Object.fromEntries(['native','docker'].map(profile=>[profile,temporaryCommand(profile,'describe',run).existing_files_count]));
 fs.writeFileSync(path.join(out,'raw/b03-fix/green-attempt-'+attempt+'.json'),JSON.stringify(result,null,2));
}
assert.equal(result.profiles.length,2);console.log(JSON.stringify(result));
