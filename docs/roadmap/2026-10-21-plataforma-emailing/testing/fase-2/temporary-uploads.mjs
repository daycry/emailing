import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import {createHash,randomUUID} from 'node:crypto';
const out=path.dirname(fileURLToPath(import.meta.url)),root=path.resolve(out,'../../../../..');
export const registryDir=path.join(out,'raw/artifacts/temporary-uploads');
fs.mkdirSync(registryDir,{recursive:true});
export function temporaryCommand(profile,action,run,payload={}){
 const code=fs.readFileSync(path.join(out,'temporary-uploads.php'),'utf8').replace(/^<\?php\s*/,'');
 const args=profile==='native'?['php',[path.join(out,'temporary-uploads.php'),profile,action,run,JSON.stringify(payload)]]:['docker',['compose','-p','emailing-qa','-f','compose.yaml','-f','tests/.runtime/compose.qa.yaml','exec','-T','emailing-php','php','-r',code,profile,action,run,JSON.stringify(payload)]];
 const receipts=path.join(registryDir,'commands');fs.mkdirSync(receipts,{recursive:true});const id=run+'-'+profile+'-'+action+'-'+randomUUID();
 fs.writeFileSync(path.join(receipts,id+'.input.json'),JSON.stringify({profile,action,run,payload},null,2));
 const result=spawnSync(args[0],args[1],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:16*1024*1024,timeout:60000,env:{...process.env,PHP_INI_SCAN_DIR:path.join(root,'storage/php-conf')}});
 fs.writeFileSync(path.join(receipts,id+'.stdout.txt'),result.stdout||'');fs.writeFileSync(path.join(receipts,id+'.stderr.txt'),result.stderr||'');
 if(result.status!==0||result.error)throw new Error('Temporary command failed; private receipt '+id);
 try{return JSON.parse(result.stdout);}catch{throw new Error('Temporary command returned non-JSON; private receipt '+id);}
}
export async function recordTemporaryUpload(page,f,name,buffer,key='owner',capturedHandle=null){
 const origin=new URL(page.url()).origin,profile=origin==='http://127.0.0.1:8082'?'native':origin==='http://127.0.0.1:8083'?'docker':null;
 if(!profile)throw new Error('Temporary upload registry requires an approved local profile');
 const handle=capturedHandle||await page.evaluate(()=>window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).$get('file'));
 const sessionHash=createHash('sha256').update(JSON.stringify(await page.context().cookies(origin))).digest('hex');
 const entry=temporaryCommand(profile,'record',f.run,{handle,response_handle:capturedHandle!==null,original_name:name,sha256:createHash('sha256').update(buffer).digest('hex'),user_id:f.users[key].id,account_id:f.accounts.A.id,session_hash:sessionHash});
 const registry=path.join(registryDir,f.run+'.json');const entries=fs.existsSync(registry)?JSON.parse(fs.readFileSync(registry)):[];
 const existing=entries.find(e=>e.profile===profile&&e.handle===entry.handle);
 if(existing){if(existing.user_id!==entry.user_id||existing.account_id!==entry.account_id||JSON.stringify(existing.files)!==JSON.stringify(entry.files))throw new Error('Temporary upload registry collision');return existing;}
 entries.push(entry);fs.writeFileSync(registry,JSON.stringify(entries,null,2));return entry;
}
export function ownedUploadName(name,f){if(!f?.run)throw new Error('Upload fixture UUID required');const ext=path.extname(name);return name.includes(f.run)?name:name.slice(0,name.length-ext.length)+'-'+f.run+ext;}
export async function recordUploadResponse(page,f,response,name,buffer,key='owner'){
 if(!response.ok())return;
 const data=await response.json();
 for(const signed of data.paths||[])await recordTemporaryUpload(page,f,name,buffer,key,'livewire-file:'+signed);
}
export async function trackedUpload(page,f,name,buffer,key='owner'){
 name=ownedUploadName(name,f);
 const pending=page.waitForResponse(r=>r.request().method()==='POST'&&new URL(r.url()).pathname.endsWith('/upload-file'));
 await page.locator('input[type=file]').setInputFiles({name,mimeType:'text/csv',buffer});
 const response=await pending;await recordUploadResponse(page,f,response,name,buffer,key);return response.status();
}
export function cleanupTemporaryRun(run){
 const registry=path.join(registryDir,run+'.json');if(!fs.existsSync(registry))return [];
 const receipts=JSON.parse(fs.readFileSync(registry)).map(entry=>temporaryCommand(entry.profile,'cleanup',run,entry));
 fs.writeFileSync(path.join(registryDir,run+'.cleanup.json'),JSON.stringify(receipts,null,2));return receipts;
}
