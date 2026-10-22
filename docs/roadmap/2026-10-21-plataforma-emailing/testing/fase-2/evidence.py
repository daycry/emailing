from pathlib import Path
import json,re,subprocess,hashlib,sys
out=Path(__file__).resolve().parent
root=out.parents[4]
plugin=Path.home()/'.codex/plugins/cache/daycry/custom-agents/1.22.0'
attempt=int(sys.argv[1]) if len(sys.argv)>1 else 1
original=out/f'raw/artifacts/results-attempt-{attempt}-original.json'
data=json.loads(original.read_text(encoding='utf-8'))
count=0
def redact(value):
 global count
 if isinstance(value,dict):return {k:redact(v) for k,v in value.items()}
 if isinstance(value,list):return [redact(v) for v in value]
 if isinstance(value,str):
  new=re.sub(r'(?i)(?:[A-Z]:[/\\]+Users[/\\]+|/Users/|/home/)[^/\\\r\n\t\"\'<> ]+', '<USER_PROFILE>',value)
  new=re.sub(r'(?:qa-secret|qa-user|replacement-secret|replacement-user|error-secret|error-user)-[a-f0-9-]{36}', '<SYNTHETIC_CREDENTIAL>',new)
  if new!=value:count+=1
  return new
 return value
public=redact(data)
assert public['stats']==data['stats']
path=out/f'raw/results-attempt-{attempt}.json'
path.write_text(json.dumps(public,ensure_ascii=False,indent=2),encoding='utf-8')
(out/'raw/results.json').write_bytes(path.read_bytes())
manifest={'original_sha256':hashlib.sha256(original.read_bytes()).hexdigest(),'public_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'redacted_strings':count,'stats_unchanged':True,'original_private':True}
(out/f'raw/redaction-attempt-{attempt}.json').write_text(json.dumps(manifest,indent=2))
with (out/f'raw/qa-gate-attempt-{attempt}.json').open('wb') as log:
 p=subprocess.run(['python',str(plugin/'agent-kits/qa/qa-gate.py'),str(original)],cwd=root,stdout=log,stderr=subprocess.STDOUT)
qa=json.loads((out/f'raw/qa-gate-attempt-{attempt}.json').read_text(encoding='utf-8'))
(out/'raw/qa-gate.json').write_text(json.dumps(qa,indent=2),encoding='utf-8')
print(json.dumps({'gate_exit':p.returncode,'gate':qa,'redacted_strings':count}))
