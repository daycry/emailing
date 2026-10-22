import fs from 'node:fs';import path from 'node:path';import assert from 'node:assert/strict';import {fileURLToPath} from 'node:url';
import {registryDir,temporaryCommand} from './temporary-uploads.mjs';
const out=path.dirname(fileURLToPath(import.meta.url)),workspace=path.resolve(out,'../../../../..');
export function nativeJunctionProbe(entry){
const run=entry.run;
const root=entry.root,link=path.join(root,'b03-controlled-link-'+run),target=path.join(path.dirname(root),'b03-controlled-outside-'+run),marker=path.join(target,'controlled.txt');
for(const p of [root,link,target,marker]){const relative=path.relative(workspace,path.resolve(p));assert(!relative.startsWith('..')&&!path.isAbsolute(relative));}
assert(!fs.existsSync(link)&&!fs.existsSync(target));let made=false;
try{
 fs.mkdirSync(target);fs.writeFileSync(marker,'Controlled '+run);fs.symlinkSync(target,link,'junction');made=true;
 assert(fs.lstatSync(link).isSymbolicLink());assert(path.relative(root,fs.realpathSync(link)).startsWith('..'));
 const result=temporaryCommand('native','path-guard-observe',run,{entry,name:path.basename(link)});
 assert.equal(result.rejected,true);assert(['Temporary symlink rejected','Temporary canonical path rejected'].includes(result.reason));
 assert.equal(fs.readFileSync(marker,'utf8'),'Controlled '+run);
 const receipt={node:process.version,fixture:'Node fs.symlinkSync junction',actual_outside_root:true,php_guard_rejected:true,reason:result.reason,target_preserved:true};
 return receipt;
}finally{if(made){assert(fs.lstatSync(link).isSymbolicLink());fs.unlinkSync(link);}if(fs.existsSync(marker)){assert.equal(fs.readFileSync(marker,'utf8'),'Controlled '+run);fs.unlinkSync(marker);}if(fs.existsSync(target))fs.rmdirSync(target);}
}
if(process.argv[1]&&path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
 const run=process.argv[2];if(!run)throw new Error('An exact owned registry UUID is required');
 const entry=JSON.parse(fs.readFileSync(path.join(registryDir,run+'.json'))).find(e=>e.profile==='native');
 const receipt=nativeJunctionProbe(entry);console.log(JSON.stringify(receipt));
}
