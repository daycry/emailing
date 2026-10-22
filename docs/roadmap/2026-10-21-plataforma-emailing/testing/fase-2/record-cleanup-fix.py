from pathlib import Path
import hashlib,json,datetime
out=Path(__file__).resolve().parent
p=out/'cleanup-owned.py'
after=p.read_text(encoding='utf-8')
replacement="  $ownedShape=$disk==='contact-imports' ? preg_match('~^sources/[a-f0-9-]{36}\\.csv$~D',$p) : (preg_match('~^[0-9]+/[a-f0-9-]{36}\\.eml$~D',$p) && in_array((int)explode('/',$p)[0],$ids,true));\n  if(!str_starts_with($path,$root)||str_contains($p,'..')||!$ownedShape)throw new RuntimeException('QA artifact path mismatch');"
original="  if(!str_starts_with($path,$root)||str_contains($p,'..')||!in_array((int)explode('/',$p)[0],$ids,true))throw new RuntimeException('QA artifact path mismatch');"
lines=after.splitlines(keepends=True)
index=next(i for i,line in enumerate(lines) if line.startswith('  $ownedShape='))
before=''.join(lines[:index]+[original+'\n']+lines[index+2:])
manifest=json.loads((out/'raw/harness-sha256-attempt-1.json').read_text())
assert hashlib.sha256(before.encode()).hexdigest()==manifest['cleanup-owned.py']
(out/'raw/artifacts/cleanup-owned-attempt-1-original.py').write_bytes(before.encode('utf-8'))
receipt={'changed_at_utc':datetime.datetime.fromtimestamp(p.stat().st_mtime,datetime.timezone.utc).isoformat(),'before_sha256':manifest['cleanup-owned.py'],'after_sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'scope':'Cleanup-only path ownership guard; no setup or assertions changed','during_suite':True,'original_reconstructed_verified_against_frozen_hash':True}
(out/'raw/harness-cleanup-fix-attempt-1.json').write_text(json.dumps(receipt,indent=2))
print(json.dumps(receipt))
