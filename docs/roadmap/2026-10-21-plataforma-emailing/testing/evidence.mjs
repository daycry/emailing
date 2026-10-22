// Preserve real runner output; redact private paths and link secrets only.
import fs from 'node:fs';
import path from 'node:path';
import {homedir} from 'node:os';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
const here=path.dirname(fileURLToPath(import.meta.url));
const root=path.resolve(here,'../../../..');
const kit=process.env.CUSTOM_AGENTS_ROOT||path.join(homedir(),'.codex/plugins/cache/daycry/custom-agents/1.22.0');
const raw=path.join(here,'fase-1/raw');
function safeString(s){
 for(const h of [homedir(),homedir().replaceAll('\\','/'),homedir().replaceAll('\\','\\\\')])s=s.split(h).join('<user-home>');
 return s.replace(/[A-Za-z]:[\\/]Users[\\/][^\\/\r\n" ]+/g,'<user-home>').replace(/(\/reset-password\/)[a-zA-Z0-9]+/g,'$1[REDACTED]').replace(/(\/invitations\/)[a-zA-Z0-9]{64}/g,'$1[REDACTED]');
}
function sanitize(v,key=''){if(typeof v==='string'){const s=safeString(v);return /^(message|stack|value)$/.test(key)?s.replace(/\b[a-f0-9]{32}(?:-new|-twice)*\b/g,'[REDACTED-FIXTURE-PASSWORD]'):s;}if(Array.isArray(v))return v.map(x=>sanitize(x,key));if(v&&typeof v==='object')return Object.fromEntries(Object.entries(v).map(([k,x])=>[k,sanitize(x,k)]));return v;}
export function preserve(name){
 const source=path.join(raw,'results.json');const original=JSON.parse(fs.readFileSync(source));
 fs.writeFileSync(path.join(here,'raw/artifacts',name+'-original.json'),JSON.stringify(original,null,2));
 const safe=sanitize(original);
 function strings(v){if(typeof v==='string')return [v];if(Array.isArray(v))return v.flatMap(strings);if(v&&typeof v==='object')return Object.values(v).flatMap(strings);return [];}
 const originals=strings(original),publicStrings=strings(safe),passwords=new Set(originals.flatMap(s=>[...s.matchAll(/fill\("([a-f0-9]{32}(?:-new|-twice)*)"\)/g)].map(m=>m[1])));
 function results(suites){return suites.flatMap(s=>[...(s.specs||[]).flatMap(p=>p.tests.map(t=>({id:t.testId,project:t.projectName,status:t.status,results:t.results.map(r=>({status:r.status,retry:r.retry,duration:r.duration}))}))),...results(s.suites||[])]);}
 const audit={statsUnchanged:JSON.stringify(original.stats)===JSON.stringify(safe.stats),testStatusesAndDurationsUnchanged:JSON.stringify(results(original.suites))===JSON.stringify(results(safe.suites)),errorFixturePasswordsFound:passwords.size,publicFixturePasswordOccurrences:[...passwords].filter(p=>publicStrings.some(s=>s.includes(p))).length,publicPrivateProfilePaths:publicStrings.filter(s=>/[A-Za-z]:[\\/]Users[\\/][^\\/\r\n" ]+/.test(s)).length};
 if(!audit.statsUnchanged||!audit.testStatusesAndDurationsUnchanged||audit.publicFixturePasswordOccurrences||audit.publicPrivateProfilePaths)throw new Error('Redaction audit failed');
 fs.writeFileSync(path.join(raw,name+'-redaction.json'),JSON.stringify(audit,null,2));
 fs.writeFileSync(source,JSON.stringify(safe,null,2));fs.writeFileSync(path.join(raw,name+'.json'),JSON.stringify(safe,null,2));return safe;
}
function run(name,cmd,args){const p=spawnSync(cmd,args,{cwd:root,encoding:'utf8',windowsHide:true,env:{...process.env,PYTHONUTF8:'1'}});fs.writeFileSync(path.join(raw,name+'.txt'),safeString(p.stdout||'')+safeString(p.stderr||''));return {exit:p.status,stdout:safeString(p.stdout||''),stderr:safeString(p.stderr||'')};}
if(process.argv[2]==='preserve'){const data=preserve(process.argv[3]);console.log(JSON.stringify({stats:data.stats,errors:data.errors?.length||0}));}
if(process.argv[2]==='history'){
 const historyFile=path.join(raw,'gates-history.json');
 const all=fs.existsSync(historyFile)?JSON.parse(fs.readFileSync(historyFile)):{};
 for(const name of fs.readdirSync(raw).filter(n=>/^results-(?:attempt-\d+|cycle-\d+-attempt-\d+)\.json$/.test(n)).sort())if(!(name in all))all[name]=run('qa-gate-'+name.replace('.json',''),'python',[path.join(kit,'agent-kits/qa/qa-gate.py'),path.join(raw,name)]);
 fs.writeFileSync(path.join(raw,'gates-history.json'),JSON.stringify(all,null,2));console.log(JSON.stringify(Object.fromEntries(Object.entries(all).map(([k,v])=>[k,{exit:v.exit,gate:JSON.parse(v.stdout)}]))));
}
if(process.argv[2]==='p1'){
 const label=process.argv[3];if(!/^cycle-\d+-attempt-\d+$/.test(label))throw new Error('Unique cycle label required');
 const initiative=path.dirname(here),gates={};
 gates.guardrail=run(label+'-guardrail','C:/Program Files/Git/bin/bash.exe',['-c',`. "${path.join(kit,'agent-kits/qa/lib-guardrail.sh').replaceAll('\\','/')}"; guardrail_assert "http://127.0.0.1:8082"; guardrail_assert "http://127.0.0.1:8083"; guardrail_assert "http://127.0.0.1:8084"; guardrail_assert "http://127.0.0.1:18025"`]);
 gates.ledger=run(label+'-ledger-lint','python',[path.join(kit,'agent-kits/shared/ledger-lint.py'),path.join(initiative,'tasks.md')]);
 gates.coverage=run(label+'-coverage-check','python',[path.join(kit,'agent-kits/qa/coverage-check.py'),path.join(initiative,'tasks.md'),path.join(initiative,'test-plan.md'),path.join(initiative,'spec.md')]);
 fs.writeFileSync(path.join(raw,'gates-'+label+'-p1.json'),JSON.stringify(gates,null,2));
 console.log(JSON.stringify({exits:Object.fromEntries(Object.entries(gates).map(([k,v])=>[k,v.exit])),gwtMapped:JSON.parse(gates.coverage.stdout).gwt_cubiertos.length}));
 if(Object.values(gates).some(g=>g.exit!==0))process.exitCode=1;
}
if(process.argv[2]==='gates'){
 const initiative=path.dirname(here);
 const gates={};
 gates.guardrail=run('guardrail','C:/Program Files/Git/bin/bash.exe',['-c',`. "${path.join(kit,'agent-kits/qa/lib-guardrail.sh').replaceAll('\\','/')}"; guardrail_assert "http://127.0.0.1:8082"; guardrail_assert "http://127.0.0.1:8083"; guardrail_assert "http://127.0.0.1:8084"; guardrail_assert "http://127.0.0.1:18025"`]);
 gates.ledger=run('ledger-lint','python',[path.join(kit,'agent-kits/shared/ledger-lint.py'),path.join(initiative,'tasks.md')]);
 gates.coverage=run('coverage-check','python',[path.join(kit,'agent-kits/qa/coverage-check.py'),path.join(initiative,'tasks.md'),path.join(initiative,'test-plan.md'),path.join(initiative,'spec.md')]);
 gates.qa=run('qa-gate','python',[path.join(kit,'agent-kits/qa/qa-gate.py'),path.join(raw,'results.json')]);
 fs.writeFileSync(path.join(raw,'gates.json'),JSON.stringify(gates,null,2));
 const c=JSON.parse(gates.coverage.stdout);console.log(JSON.stringify({exits:Object.fromEntries(Object.entries(gates).map(([k,v])=>[k,v.exit])),gwtMapped:c.gwt_cubiertos.length,qa:JSON.parse(gates.qa.stdout)}));
}
