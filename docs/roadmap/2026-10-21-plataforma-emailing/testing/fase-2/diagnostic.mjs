import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';
import {homedir} from 'node:os';
import {fixture,login,cleanup,out,upload} from './specs/helpers.mjs';
const require=createRequire(import.meta.url);
const {chromium}=require(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const f=fixture(),contexts=[],receipt={};
const waitCall=(page,name)=>page.waitForResponse(r=>{try{return r.request().method()==='POST'&&JSON.parse(r.request().postData()||'{}').components?.some(c=>c.calls?.some(x=>x.method===name));}catch{return false;}});
try{
 for(const [profile,port] of [['native',8082],['docker',8083]]){
  const context=await browser.newContext({baseURL:`http://127.0.0.1:${port}`,viewport:{width:390,height:844}});contexts.push(context);const page=await context.newPage();await login(page,f);await page.goto('/team');
  const measure=()=>page.evaluate(()=>({innerWidth,scrollWidth:document.documentElement.scrollWidth,navWidth:document.querySelector('nav').scrollWidth,wrap:getComputedStyle(document.querySelector('nav')).flexWrap}));
  const before=await measure();await page.locator('nav').evaluate(el=>el.style.flexWrap='wrap');const after=await measure();receipt[profile]={overflow:{before,after,change:'Transient DOM nav flexWrap only; no source change'}};
  if(profile==='native'){
   await page.setViewportSize({width:1440,height:1000});await page.goto('/contacts/imports');const paths=[];page.on('request',r=>{if(new URL(r.url()).pathname.includes('upload'))paths.push({method:r.method(),path:new URL(r.url()).pathname});});
   await upload(page,`diagnostic-${f.run}.csv`,Buffer.from(`email,name\nqa-diagnostic-${f.run}@example.test,Diagnostic ${f.run}\n`),f);
   const file=await page.evaluate(()=>window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id')).$get('file'));
   receipt.native.upload={requests:paths,fileType:typeof file,fileIsArray:Array.isArray(file)};
   await page.goto('/templates');let created=waitCall(page,'create');await page.getByRole('button',{name:'Nueva plantilla',exact:true}).click();await created;
   for(const [name,value] of Object.entries({name:'Diagnostic first',subject:'Diagnostic',html:'<p>First</p>',text:'First'}))await page.locator(`[wire\\:model=${name}]`).fill(value);
   let saved=waitCall(page,'save');await page.getByRole('button',{name:'Guardar plantilla',exact:true}).click();await saved;
   created=waitCall(page,'create');await page.getByRole('button',{name:'Nueva plantilla',exact:true}).click();await page.locator('[wire\\:model=name]').fill('Diagnostic second');const immediate=await page.locator('[wire\\:model=name]').inputValue();await created;const afterCreate=await page.locator('[wire\\:model=name]').inputValue();
   receipt.native.createRace={valueImmediatelyAfterFill:immediate,valueAfterCreateResponse:afterCreate};
   await page.goto('/settings/mail');await page.locator('details').first().evaluate(el=>el.open=true);const beforeOpen=await page.locator('details').first().evaluate(el=>el.open);
   saved=waitCall(page,'saveSettings');await page.getByRole('button',{name:'Guardar configuración',exact:true}).click();await saved;receipt.native.settingsDetails={beforeOpen,afterSaveOpen:await page.locator('details').first().evaluate(el=>el.open)};
  }
 }
}finally{
 fs.writeFileSync(path.join(out,'raw/diagnostic-attempt-1.json'),JSON.stringify(receipt,null,2));await cleanup(f,contexts);await browser.close();
}
console.log(JSON.stringify(receipt));
