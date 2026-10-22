import path from 'node:path';
import {homedir} from 'node:os';
import {pathToFileURL} from 'node:url';
const origins=['http://127.0.0.1:8083','http://127.0.0.1:8082'];
const direct=[];
for(const origin of origins){
 const started=performance.now();
 try {
  const response=await fetch(origin+'/dashboard',{redirect:'manual',signal:AbortSignal.timeout(10000)});
  direct.push({origin,status:response.status,location:response.headers.get('location'),durationMs:Math.round(performance.now()-started)});
  await response.arrayBuffer();
 }catch(error){direct.push({origin,error:error.name,durationMs:Math.round(performance.now()-started)});}
}
const {chromium}=await import(pathToFileURL(path.join(homedir(),'.claude/tool-cache/qa/node_modules/@playwright/test/index.mjs')).href);
const browser=await chromium.launch({headless:true,executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')});
const navigation=[];
try {
 for(const origin of origins){
  const context=await browser.newContext();
  try{
   const page=await context.newPage();
   const pending=new Set();const failed=[];
   page.on('request',r=>pending.add(r));
   page.on('requestfinished',r=>pending.delete(r));
   page.on('requestfailed',r=>{pending.delete(r);failed.push({path:new URL(r.url()).pathname,error:r.failure()?.errorText});});
   const started=performance.now();let domMs=null;
   try{
    const response=await page.goto(origin+'/dashboard',{waitUntil:'domcontentloaded',timeout:30000});
    domMs=Math.round(performance.now()-started);
    await page.waitForLoadState('load',{timeout:30000});
    navigation.push({origin,status:response.status(),finalPath:new URL(page.url()).pathname,domMs,loadMs:Math.round(performance.now()-started),failed,pending:[...pending].map(r=>new URL(r.url()).pathname)});
   }catch(error){navigation.push({origin,error:error.name,domMs,durationMs:Math.round(performance.now()-started),failed,pending:[...pending].map(r=>new URL(r.url()).pathname)});}
  }finally{await context.close();}
 }
 console.log(JSON.stringify({direct,navigation}));
}finally{await browser.close();}
