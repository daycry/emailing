from pathlib import Path
import json,re,subprocess,urllib.request,urllib.error,urllib.parse,sys
out=Path(__file__).resolve().parent;root=out.parents[4];raw=out/'raw/artifacts'
attempt=int(sys.argv[1]) if len(sys.argv)>1 else 2
destination=out/f'raw/teardown-extras-attempt-{attempt}.json'
if attempt<2 or destination.exists():raise SystemExit('Refusing to overwrite historical teardown evidence')
runs=sorted({p.name[:36] for p in raw.glob('*-docker-cleanup.txt') if re.match(r'^[a-f0-9-]{36}-docker-cleanup.txt$',p.name)})
assert runs and all(re.fullmatch(r'[a-f0-9-]{36}',r) for r in runs)
receipt={'runs':len(runs),'mailpit_deleted':0,'native_temporary_deleted':0}
def request(path,method='GET',body=None):
 data=json.dumps(body).encode() if body is not None else None
 req=urllib.request.Request('http://127.0.0.1:18025'+path,data=data,method=method,headers={'Content-Type':'application/json'})
 with urllib.request.urlopen(req) as response:return json.load(response) if response.headers.get('content-type','').startswith('application/json') else response.read()
before=request('/api/v1/messages?limit=10000')['messages'];owned=set()
for run in runs:
 for message in request('/api/v1/search?query='+urllib.parse.quote(run)+'&limit=1000').get('messages',[]):
  identifier=message['ID'];detail=request('/api/v1/message/'+identifier)
  addresses=[entry.get('Address','') for entry in detail.get('To',[])]
  if not any(run in address and address.endswith('@example.test') for address in addresses):continue
  owned.add(identifier)
for identifier in sorted(owned):
 assert identifier
 request('/api/v1/messages','DELETE',{'IDs':[identifier]})
 try:request('/api/v1/message/'+identifier);raise RuntimeError('Own Mailpit message still exists')
 except urllib.error.HTTPError as error:
  if error.code!=404:raise
 receipt['mailpit_deleted']+=1
after={m['ID'] for m in request('/api/v1/messages?limit=10000')['messages']}
receipt['other_mailpit_messages_preserved']=all(m['ID'] in after for m in before if m['ID'] not in owned)
logfile=raw/f'temporary-cleanup-attempt-{attempt}.txt'
with logfile.open('wb') as log:p=subprocess.run(['node',str(out/'cleanup-temporary.mjs')],cwd=root,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
if p.returncode:raise SystemExit('Exact registered temporary cleanup failed')
temporary=json.loads(logfile.read_text())
for profile in ['native','docker']:receipt[profile+'_temporary_deleted']=sum(r['deleted'] for r in temporary['receipts'] if r['profile']==profile)
receipt['temporary_cleanup_scope']=temporary['scope']
receipt['historical_unattributed_files']='Preserved; no claim of zero global residues'
destination.write_text(json.dumps(receipt,indent=2))
print(json.dumps(receipt))
