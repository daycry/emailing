from pathlib import Path
import json,hashlib,difflib,re,subprocess
out=Path(__file__).resolve().parent;root=out.parents[4]
archive=out/'raw/artifacts/attempt-1-source';diff=[];hashes={};codes={}
for name in json.loads((out/'raw/harness-sha256-attempt-1.json').read_text()):
 before=(archive/name).read_text(encoding='utf-8');after=(out/name).read_text(encoding='utf-8')
 if before!=after:diff.extend(difflib.unified_diff(before.splitlines(True),after.splitlines(True),fromfile='attempt-1/'+name,tofile='next-attempt/'+name))
for p in [*out.glob('*.py'),*out.glob('*.php'),*out.glob('*.mjs'),*out.glob('*.ps1'),*out.glob('specs/*.mjs')]:hashes[str(p.relative_to(out))]=hashlib.sha256(p.read_bytes()).hexdigest()
patch=''.join(diff)
(out/'raw/artifacts/harness-post-attempt-1-original.patch').write_text(patch,encoding='utf-8')
patch=re.sub(r'(?i)(?:[A-Z]:[/\\]+Users[/\\]+|/Users/|/home/)[^/\\\r\n\t\"\'<> ]+','<USER_PROFILE>',patch)
(out/'raw/harness-post-attempt-1.patch').write_text(patch,encoding='utf-8')
(out/'raw/harness-post-attempt-1-sha256.json').write_text(json.dumps(hashes,indent=2))
with (out/'raw/harness-post-attempt-1-lint.txt').open('wb') as log:
 for p in [*out.glob('*.mjs'),*out.glob('specs/*.mjs')]:
  codes[str(p.relative_to(out))]=subprocess.run(['node','--check',str(p)],cwd=root,stdout=log,stderr=subprocess.STDOUT).returncode
 for p in out.glob('*.py'):
  codes[str(p.relative_to(out))]=subprocess.run(['python','-m','py_compile',str(p)],cwd=root,stdout=log,stderr=subprocess.STDOUT).returncode
(out/'raw/harness-post-attempt-1-lint.json').write_text(json.dumps(codes,indent=2))
print('Harness syntax checks:',len(codes),'failed:',sum(bool(c) for c in codes.values()))
