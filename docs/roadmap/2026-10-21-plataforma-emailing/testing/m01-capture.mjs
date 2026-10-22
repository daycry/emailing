// Visual evidence only: no fourth E2E run and no claim of a valid transfer.
import fs from 'node:fs';
import path from 'node:path';
import {homedir} from 'node:os';
import {createRequire} from 'node:module';
import {fixture,cleanup,login,shot,out} from './specs/helpers.mjs';
const require=createRequire(import.meta.url);
const {chromium}=require(path.join(process.env.QA_CACHE_DIR||path.join(homedir(),'.claude/tool-cache/qa'),'node_modules/playwright'));
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const evidence=[];
try{
 for(const [profile,baseURL] of [['native','http://127.0.0.1:8082'],['docker','http://127.0.0.1:8083']]){
  const f=fixture(),context=await browser.newContext({baseURL,viewport:{width:1440,height:1000}});context.setDefaultTimeout(30000);context.setDefaultNavigationTimeout(30000);
  try{
   const page=await context.newPage();await login(page,f);await page.goto('/team');
   for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844],['tablet',768,1024]]){
    await page.setViewportSize({width,height});await shot(page,'M01-'+name,profile);
    const measurement=await page.evaluate(()=>({innerWidth:window.innerWidth,documentWidth:document.documentElement.scrollWidth,documentHeight:document.documentElement.scrollHeight}));
    evidence.push({profile,view:name,viewport:{width,height},...measurement,horizontalOverflow:measurement.documentWidth>measurement.innerWidth,file:`screenshots/${profile}-M01-${name}.png`,captured:new Date().toISOString(),scope:'M-01 visual only; no owner transfer or E2E-04 pass claimed'});
   }
  }finally{await cleanup(f,[context]);}
 }
}finally{await browser.close();}
fs.writeFileSync(path.join(out,'fase-1/raw/m01-visual.json'),JSON.stringify(evidence,null,2));console.log(JSON.stringify({captures:evidence.length,horizontalOverflow:evidence.filter(e=>e.horizontalOverflow).length,manualAcceptance:'pending'}));
