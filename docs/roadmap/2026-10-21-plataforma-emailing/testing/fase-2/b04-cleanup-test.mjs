import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {createHash} from 'node:crypto';
import {homedir} from 'node:os';

const directory=path.dirname(fileURLToPath(import.meta.url));
const sourcePath=path.join(directory,'specs/helpers.mjs');
const source=fs.readFileSync(sourcePath,'utf8');
const stage=process.env.B04_STAGE;
assert.ok(['red','green'].includes(stage),'B04_STAGE must name an evidence stage');
const evidencePath=path.join(directory,'raw/b04-fix',stage+'.json');
assert.ok(!fs.existsSync(evidencePath),'Never overwrite earlier evidence');

// Evaluate the complete production harness module, including its actual wiring.
// Only imports with external effects are substituted; no database, PHP, Python,
// Playwright browser, fixture, or service is launched by this regression.
async function loadCleanup(calls,failures){
 const sandbox=vm.createContext({process:{env:{}},setTimeout,clearTimeout});
 const external=(name,args)=>{
  calls.push(name);
  if(failures.has(name))throw failures.get(name);
  return '';
 };
 const modules={
  'node:module':{createRequire:()=>()=>({test:{},expect:{}})},
  'node:os':{homedir},
  'node:path':{default:path},
  'node:fs':{default:fs},
  'node:child_process':{execFileSync:(executable,args)=>{
   if(executable==='python'&&path.basename(args[0])==='cleanup-owned.py')return external('owned',args);
   if(executable==='php'&&path.basename(args[0])==='resources.php'&&args[1]==='precleanup')return external('precleanup',args);
   if(executable==='php'&&path.basename(args[0])==='fixture.php'&&args[1]==='cleanup')return external('fixture',args);
   throw new Error('Unexpected external operation: '+executable+' '+JSON.stringify(args));
  }},
  'node:crypto':{randomUUID:()=>{throw new Error('Fixture setup is forbidden');}},
  'node:url':{fileURLToPath},
  '../temporary-uploads.mjs':{trackedUpload:()=>{throw new Error('Upload is forbidden');},cleanupTemporaryRun:run=>external('temporary',[run])},
 };
 const module=new vm.SourceTextModule(source,{context:sandbox,identifier:pathToFileURL(sourcePath).href,initializeImportMeta:meta=>{meta.url=pathToFileURL(sourcePath).href;}});
 await module.link(specifier=>{
  assert.ok(Object.hasOwn(modules,specifier),'Unexpected import '+specifier);
  const exports=modules[specifier];
  return new vm.SyntheticModule(Object.keys(exports),function(){for(const [key,value]of Object.entries(exports))this.setExport(key,value);},{context:sandbox});
 });
 await module.evaluate();
 return module.namespace.cleanup;
}

const scenarios=[
 {name:'all successful',fail:[]},
 {name:'temporary rejects content hash',fail:['temporary']},
 {name:'context close rejects',fail:['context-a']},
 {name:'owned files reject',fail:['owned']},
 {name:'precleanup rejects',fail:['precleanup']},
 {name:'fixture cleanup rejects',fail:['fixture']},
 {name:'multiple independent failures',fail:['context-a','context-b','temporary','owned','precleanup','fixture']},
];
const results=[];
for(const scenario of scenarios){
 const calls=[];
 const failures=new Map(scenario.fail.map(name=>[name,new Error(name==='temporary'?'Registered temporary content changed':'Original '+name+' failure')]));
 const cleanup=await loadCleanup(calls,failures);
 const contexts=['context-a','context-b'].map(name=>({close:async()=>{calls.push(name);if(failures.has(name))throw failures.get(name);}}));
 let received;
 try{await cleanup({run:'00000000-0000-4000-8000-000000000004'},contexts);}catch(error){received=error;}
 const issues=[];
 try{assert.deepEqual(calls,['context-a','context-b','temporary','owned','precleanup','fixture']);}catch(error){issues.push(error.message);}
 if(!failures.size){if(received)issues.push('Unexpected rejection: '+received.message);}
 else{
  if(!received)issues.push('Cleanup failures must reject the caller');
  const originals=received?.errors??(received?[received]:[]);
  for(const original of failures.values())if(!originals.includes(original))issues.push('Original cause lost: '+original.message);
  if(originals.length!==failures.size)issues.push('Unexpected number of retained errors: '+originals.length);
 }
 results.push({name:scenario.name,passed:issues.length===0,calls,expected_error_messages:[...failures.values()].map(error=>error.message),received_error:received?.message??null,retained_errors:[...(received?.errors??(received?[received]:[]))].map(error=>error.message),issues});
}
const receipt={stage,node:process.version,subject:'complete specs/helpers.mjs module with external effects stubbed',helpers_sha256:createHash('sha256').update(source).digest('hex'),tests:results.length,passed:results.filter(result=>result.passed).length,failed:results.filter(result=>!result.passed).length,results};
fs.mkdirSync(path.dirname(evidencePath),{recursive:true});
fs.writeFileSync(evidencePath,JSON.stringify(receipt,null,2)+'\n',{flag:'wx'});
console.log(JSON.stringify(receipt,null,2));
process.exitCode=receipt.failed?1:0;
