import {test,expect,fixture,login,shot,lw,cleanup,resources,resource,uiCall,upload} from './helpers.mjs';
import {trackedUpload,recordUploadResponse,registryDir,temporaryCommand} from '../temporary-uploads.mjs';
import fs from 'node:fs';
import path from 'node:path';
const csv=(f)=>Buffer.from('\uFEFFemail,name,consent_status,consent_source,consented_at\r\n'+
 `duplicate-${f.run}@example.test,Overwritten,revoked,,\r\n`+
 `new-${f.run}@example.test,"QA, quoted\nname",granted,Form,2025-01-10T12:30\r\n`+
 `invalid-address,Invalid,unknown,,\r\n`+
 `=SUM(1),Formula,unknown,,\r\n`);
test('E2E-05 CA-02/03/13 shared CRUD CSV BOM quoted lines duplicate consent account scopes',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[];try{
  await login(page,f);await page.goto('/contacts');
  await page.getByRole('button',{name:'Nuevo contacto',exact:true}).click();
  await page.locator('[wire\\:model=email]').fill(`created-${f.run}@example.test`);
  await page.locator('[wire\\:model=name]').fill('Created through browser');
  await page.getByRole('button',{name:'Guardar contacto',exact:true}).click();
  await expect(page.getByRole('status')).toBeVisible();
  await page.getByRole('button',{name:'Nueva lista',exact:true}).click();
  await page.locator('[wire\\:model=listName]').fill('Shared browser list');
  await page.getByRole('button',{name:'Guardar lista',exact:true}).click();
  const shared=resources(f).contact_lists.find(x=>x.name==='Shared browser list');expect(shared.account_id).toBe(f.accounts.A.id);
  const teammate=await browser.newContext({baseURL});contexts.push(teammate);const tp=await teammate.newPage();await login(tp,f,'marketer');await tp.goto('/contacts');
  await expect(tp.locator('body')).toContainText('Shared browser list');await expect(tp.locator('body')).toContainText('Created through browser');
  await shot(page,'05-contacts-shared',info.project.name);
  await page.goto('/contacts/imports');await uiCall(page,'selectList',[shared.id]);
  await upload(page,'mixed.csv',csv(f),f);await page.getByRole('button',{name:'Importar y revisar',exact:true}).click();
  await expect(page.getByRole('status')).toContainText('Importaci');
  let s=resources(f);const report=s.contact_imports.at(-1);expect({total:report.total,imported:report.imported,invalid:report.invalid,duplicates:report.duplicates}).toEqual({total:4,imported:1,invalid:2,duplicates:1});
  const duplicate=s.contacts.find(x=>x.id===f.contacts.A);expect(duplicate.name).toBe('Original name');expect(duplicate.consent_status).toBe('granted');expect(duplicate.consent_source).toBe('Fixture evidence');
  const added=s.contacts.find(x=>x.email===`new-${f.run}@example.test`&&x.account_id===f.accounts.A.id);expect(added.name).toBe('QA, quoted\nname');expect(added.consented_at).toContain('2025-01-10');
  expect(s.contact_list_memberships.filter(x=>x.contact_list_id===shared.id).map(x=>x.contact_id)).toEqual([added.id]);
  await expect(page.locator('tbody tr')).toHaveCount(4);await shot(page,'05-import-report',info.project.name);
  const dl=await page.request.get(`/contacts/imports/${report.id}/download`);expect(dl.status()).toBe(200);const downloaded=await dl.text();expect(downloaded).toContain("'=sum(1)");
  const publicReport=await page.request.get('/storage/'+report.id+'.csv');expect(publicReport.status()).toBe(403);
  const publicBody=await publicReport.text();expect(publicBody).not.toContain(`new-${f.run}@example.test`);expect(publicBody).not.toContain('QA, quoted');expect(publicBody).not.toBe(downloaded);
  const foreign=await browser.newContext({baseURL});contexts.push(foreign);const fp=await foreign.newPage();await login(fp,f,'other');await fp.goto('/contacts/imports');
  expect((await fp.request.get(`/contacts/imports/${report.id}/download`)).status()).toBe(404);
  expect((await lw(fp,'selectReport',[report.id])).status).toBe(404);
  await page.goto('/contacts');expect((await lw(page,'editContact',[f.contacts.B])).status).toBe(404);await page.reload();
  expect((await lw(page,'editList',[f.lists.B])).status).toBe(404);
  await tp.goto('/contacts');const before=resources(f);resource('revoke-read',f);
  const denied=await lw(tp,'saveContact',[],{email:`forbidden-${f.run}@example.test`,name:'Forbidden',consentStatus:'unknown'});expect(denied.status).toBe(403);
  s=resources(f);expect(s.contacts).toEqual(before.contacts);expect(s.audit_events).toEqual(before.audit_events);
  resource('restore-read',f);
  const ro=await browser.newContext({baseURL});contexts.push(ro);const rp=await ro.newPage();await login(rp,f,'readonly');await rp.goto('/contacts');
  expect((await lw(rp,'saveContact',[],{email:`readonly-${f.run}@example.test`,name:'Blocked'})).status).toBe(403);
 }finally{await cleanup(f,contexts);}
});

function nearLimitCSV(f){
 const header='email,name,consent_status,consent_source,consented_at\n',n=9990,target=5*1024*1024-4096;
 const domain='a'.repeat(63)+'.'+'b'.repeat(63)+'.'+'c'.repeat(63)+'.test';
 const rows=Array.from({length:n},(_,i)=>[`q${i}-${f.run}@${domain}`,'N'.repeat(150),'granted','S','2025-01-10T12:30']);
 let size=Buffer.byteLength(header+rows.map(r=>r.join(',')+'\n').join(''));
 for(let i=0;size<target;i=(i+1)%n){const extra=Math.min(254,target-size);rows[i][3]+='S'.repeat(extra);size+=extra;}
 const result=Buffer.from(header+rows.map(r=>r.join(',')+'\n').join(''));expect(result.length).toBe(target);return result;
}
test('E2E-05 CA-02 HTTP actual CSV near 5 MiB accepted 9990 rows paginated oversize rejected',async({page},info)=>{
 const f=fixture();try{
  await login(page,f);await page.goto('/contacts/imports');
  const data=nearLimitCSV(f);await upload(page,'near-limit.csv',data,f);
  await page.getByRole('button',{name:'Importar y revisar',exact:true}).click();
  await expect(page.getByRole('status')).toContainText('Importaci',{timeout:120000});
  const s=resources(f),report=s.contact_imports.at(-1);expect(report.total).toBe(9990);expect(report.imported).toBe(9990);expect(report.invalid).toBe(0);
  await expect(page.locator('tbody tr')).toHaveCount(25);expect((await page.content()).length).toBeLessThan(500000);
  await shot(page,'05-near-limit-paginated',info.project.name);
  const before=s.contact_imports.length;await trackedUpload(page,f,'oversize.csv',Buffer.concat([data,Buffer.alloc(8192,10)]));
  await expect(page.locator('body')).toContainText(/5120|5 MiB|5,00 MiB|demasiado|kilobytes/i,{timeout:45000});
  expect(resources(f).contact_imports).toHaveLength(before);
  await shot(page,'05-oversize-rejected',info.project.name);
 }finally{await cleanup(f);}
});

test('E2E-05 CA-12/13 upload requires auth current scopes private handle bound to actor account',async({page,browser,baseURL},info)=>{
 const f=fixture(),contexts=[];try{
  await login(page,f,'marketer');await page.goto('/contacts/imports');
  let uploadURL;page.on('request',r=>{if(new URL(r.url()).pathname.endsWith('/upload-file'))uploadURL=r.url();});
  await upload(page,'owned.csv',csv(f),f,'marketer');expect(uploadURL).toBeTruthy();
  const file=await page.evaluate(()=>window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).$get('file'));
  expect(file).toBeTruthy();
  const other=await browser.newContext({baseURL});contexts.push(other);const op=await other.newPage();await login(op,f,'other');await op.goto('/contacts/imports');
  // Have Livewire authenticate and hydrate the real owner's reference in the
  // foreign session. A plain string update on file=null never reaches the binding.
  await uiCall(op,'_finishUpload',['file',[file.slice('livewire-file:'.length)],false]);
  const hydrated=await op.evaluate(()=>{const c=window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));const s=JSON.parse(c.__instance.snapshotEncoded);return {file:c.$get('file'),synth:s.data.file[1].s};});
  expect(hydrated).toEqual({file,synth:'fil'});
  const profile=new URL(page.url()).port==='8083'?'docker':'native';
  const entry=JSON.parse(fs.readFileSync(path.join(registryDir,f.run+'.json'))).find(e=>e.profile===profile);
  expect(entry).toBeTruthy();
  const beforeFile=temporaryCommand(entry.profile,'inspect',f.run,entry),before=resources(f);
  expect((await lw(op,'import')).status).toBe(403);
  expect(resources(f)).toEqual(before);
  expect(temporaryCommand(entry.profile,'inspect',f.run,entry)).toEqual(beforeFile);
  const verified=temporaryCommand(entry.profile,'record',f.run,{handle:entry.handle,original_name:entry.original_name,sha256:entry.files.find(x=>x.name===entry.filename).sha256,user_id:entry.user_id,account_id:entry.account_id,session_hash:entry.session_hash});
  expect(verified.files).toEqual(entry.files);
  const anonymous=await browser.newContext({baseURL});contexts.push(anonymous);
  const unauthed=await anonymous.request.post(uploadURL,{multipart:{'files[]':{name:`unauth-${f.run}.csv`,mimeType:'text/csv',buffer:csv(f)}}});expect([401,403,419]).toContain(unauthed.status());
  resource('revoke-read',f);
  expect((await lw(page,'import')).status).toBe(403);expect(resources(f)).toEqual(before);
  const csrf=await page.locator('meta[name=csrf-token]').getAttribute('content');const denied=await page.request.post(uploadURL,{headers:{'X-CSRF-TOKEN':csrf},multipart:{'files[]':{name:`revoked-${f.run}.csv`,mimeType:'text/csv',buffer:csv(f)}}});await recordUploadResponse(page,f,denied,`revoked-${f.run}.csv`,csv(f),'marketer');expect(denied.status()).toBe(403);
  await shot(op,'05-other-account-empty',info.project.name);
 }finally{await cleanup(f,contexts);}
});
