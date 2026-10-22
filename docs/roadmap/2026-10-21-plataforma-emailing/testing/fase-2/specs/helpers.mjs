import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import path from 'node:path';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import {trackedUpload,cleanupTemporaryRun} from '../temporary-uploads.mjs';
const require=createRequire(import.meta.url);
export const {test,expect}=require(path.join(process.env.QA_CACHE_DIR||path.join(homedir(),'.claude/tool-cache/qa'),'node_modules/@playwright/test'));
export const out=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const root=path.resolve(out,'../../../../..');
export function command(action,run){return execFileSync('php',[path.join(out,'fixture.php'),action,run],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000,env:{...process.env,PHP_INI_SCAN_DIR:path.join(root,'storage/php-conf')}}).trim();}
export function fixture(){const run=randomUUID();command('setup',run);resource('setup',{run});return JSON.parse(fs.readFileSync(path.join(out,'raw/artifacts',run+'.json')));}
export function state(f){return JSON.parse(command('status',f.run));}
export async function closeContext(context){let timer;try{await Promise.race([context.close(),new Promise((_,reject)=>{timer=setTimeout(()=>reject(new Error('QA context close exceeded 10 seconds')),10000);})]);}finally{clearTimeout(timer);}}
export async function cleanup(f,contexts=[]){
 const errors=[];
 const results=await Promise.allSettled(contexts.map(closeContext));
 for(const result of results)if(result.status==='rejected')errors.push(result.reason);
 const operations=[
  ()=>cleanupTemporaryRun(f.run),
  ()=>execFileSync('python',[path.join(out,'cleanup-owned.py'),f.run],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000}),
  ()=>resource('precleanup',f),
  ()=>command('cleanup',f.run),
 ];
 for(const operation of operations){try{await operation();}catch(error){errors.push(error);}}
 if(errors.length)throw new AggregateError(errors,'QA cleanup failed: '+errors.length+' operation(s)');
}
export async function login(page,f,key='owner'){await page.goto('/login');await page.locator('[name=email]').fill(f.users[key].email);await page.locator('[name=password]').fill(f.password);await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:/Acceder/}).click()]);}
export async function shot(page,name,project){await page.evaluate(()=>window.scrollTo({top:0,left:0,behavior:'instant'}));await page.screenshot({path:path.join(out,'screenshots',project+'-'+name+'.png'),fullPage:true});}
export async function settle(page){await expect(page.locator('[wire\\:id]')).toBeVisible();await page.waitForFunction(()=>!!window.Livewire);}
export async function lw(page,method,params=[],updates={}){
 await settle(page);
 return page.evaluate(async({method,params,updates})=>{
  const el=document.querySelector('[wire\\:id]');
  const csrf=document.querySelector('meta[name=csrf-token]').content;
  const endpoint=new URL(document.querySelector('[data-update-uri]').getAttribute('data-update-uri'),location.href).href;
  const r=await fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-Livewire':'','Accept':'application/json'},body:JSON.stringify({_token:csrf,components:[{snapshot:el.getAttribute('wire:snapshot'),updates,calls:[{path:'',method,params}]}]})});
  return {status:r.status,body:await r.text()};
 },{method,params,updates});
}
export async function post(page,url,data={}){return page.evaluate(async({url,data})=>{const csrf=document.querySelector('meta[name=csrf-token]')?.content||document.querySelector('[name=_token]')?.value;const r=await fetch(url,{method:'POST',redirect:'manual',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:new URLSearchParams({_token:csrf,...data})});return {status:r.status,body:await r.text()};},{url,data});}
export async function mailbox(page,email,kind,exclude=[]){
 let result;
 await expect.poll(async()=>{
  const r=await page.request.get('http://127.0.0.1:18025/api/v1/search?query='+encodeURIComponent('to:'+email));
  const j=await r.json();
  for(const m of j.messages||[]){if(exclude.includes(m.ID))continue;const d=await(await page.request.get('http://127.0.0.1:18025/api/v1/message/'+m.ID)).json();const body=(d.Text||'')+' '+(d.HTML||'');const pattern=kind==='reset'?/https?:\/\/[^\s"<>]+\/reset-password\/[^\s"<>]+/:/https?:\/\/[^\s"<>]+\/invitations\/[a-zA-Z0-9]{64}/;const match=body.match(pattern);if(match){result={id:m.ID,url:match[0].replace(/&amp;/g,'&')};return true;}}
  return false;
 },{timeout:30000,message:'Mailpit must receive the real QA notification'}).toBe(true);
 return result;
}
export function localLink(link,base){const u=new URL(link);if(!['127.0.0.1','localhost'].includes(u.hostname))throw new Error('Notification URL is not local');return base+u.pathname+u.search;}
export async function invite(page,f,key,role='read-only'){
 await page.locator('[wire\\:model=email]').fill(f.users[key].email);
 await page.locator('[wire\\:model\\.live=role]').selectOption(role);
 const endpoint=new URL(await page.locator('[data-update-uri]').getAttribute('data-update-uri'),page.url()).href;
 const r=page.waitForResponse(r=>r.url()===endpoint&&r.request().method()==='POST');
 await page.getByRole('button',{name:'Enviar invitación',exact:true}).click();expect((await r).status()).toBe(200);
 await expect(page.getByRole('status')).toContainText('Invitación enviada');
}

export function resource(action,f,extra=[]){return execFileSync('php',[path.join(out,'resources.php'),action,f.run,...extra],{cwd:root,encoding:'utf8',windowsHide:true,maxBuffer:32*1024*1024,timeout:60000,env:{...process.env,PHP_INI_SCAN_DIR:path.join(root,'storage/php-conf')}}).trim();}
export function resources(f){return JSON.parse(resource('status',f));}
export async function uiCall(page,method,params=[],updates={}){await settle(page);return page.evaluate(async ({method,params,updates})=>{const c=window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));for(const [k,v] of Object.entries(updates))c.$set(k,v,false);return await c.$call(method,...params);},{method,params,updates});}
export async function upload(page,name,buffer,f,key='owner'){await trackedUpload(page,f,name,buffer,key);await expect.poll(()=>page.evaluate(()=>{const el=document.querySelector('[wire\\:id]');const c=window.Livewire.find(el.getAttribute('wire:id'));return !!c.$get('file');}),{timeout:45000}).toBe(true);}

export async function clickCall(page,method,action){const wait=page.waitForResponse(r=>{try{return r.request().method()==='POST'&&JSON.parse(r.request().postData()||'{}').components?.some(c=>c.calls?.some(x=>x.method===method));}catch{return false;}});await action();const response=await wait;expect(response.status()).toBe(200);await page.waitForFunction(()=>{const c=window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));return c!==undefined;});}
