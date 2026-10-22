import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const require=createRequire(import.meta.url);
const {defineConfig}=require(path.join(process.env.QA_CACHE_DIR||path.join(homedir(),'.claude/tool-cache/qa'),'node_modules/@playwright/test'));
const here=path.dirname(fileURLToPath(import.meta.url));
export default defineConfig({
 testDir:'./specs',timeout:360000,expect:{timeout:15000},fullyParallel:false,workers:1,retries:0,
 outputDir:'./raw/artifacts/playwright',
 reporter:[['list'],['json',{outputFile:path.join(here,'raw/artifacts',`results-attempt-${process.env.QA_ATTEMPT||1}-original.json`)}]],
 use:{headless:true,viewport:{width:1440,height:1000},actionTimeout:30000,navigationTimeout:30000,trace:'off',video:'off',screenshot:'off',
 launchOptions:{executablePath:path.join(process.env.LOCALAPPDATA,'ms-playwright/chromium-1243/chrome-win64/chrome.exe')}},
 projects:[{name:'native',use:{baseURL:'http://127.0.0.1:8082'}},{name:'docker',use:{baseURL:'http://127.0.0.1:8083'}}]
});
