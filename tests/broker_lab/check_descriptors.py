"""Exercise pre-bootstrap refusals only; deliberately no SQL/broker runtime."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
import json
import os
import subprocess
import uuid

ROOT=Path(__file__).resolve().parents[2]
BASE=ROOT/'scripts/broker_lab'
DATA_ROOT=ROOT/'storage/broker-lab-evidence'
DATA_ROOT.mkdir(parents=True,exist_ok=True)
run=str(uuid.uuid4())
directory=ROOT/'storage'/('broker-lab-'+run)/'a'
directory.mkdir(parents=True)
descriptor={'execute':True,'mode':'init','run_id':run,'password':'a'*64,'broker_port':5672,'directory':str(directory)}
policy={'account':{'minute':10,'day':200},'pool':{'minute':20,'day':500}}
env=os.environ.copy()
env.update(APP_ENV='production',DB_DATABASE='emailing',DB_USERNAME='emailing',DB_URL='')
checks=0
try:
    cases=[({},None),(dict(descriptor,mode='drop-all'),'InvalidArgumentException'),
           (dict(descriptor,password='bad'),'InvalidArgumentException'),
           (dict(descriptor,unexpected=True),'InvalidArgumentException'),
           (dict(descriptor,directory=str(ROOT)),'InvalidArgumentException'),
           (descriptor,'RuntimeException'),(dict(descriptor,mode='schema'),'RuntimeException'),
           (dict(descriptor,mode='probe',quota_policy=policy,cut_at='2026-02-30T00:00:00.000000Z'),'InvalidArgumentException'),
           (dict(descriptor,mode='probe',quota_policy=policy,cut_at='2026-11-03T00:00:00.000000Z'),'RuntimeException'),
           (dict(descriptor,mode='produce',sequence=1,limit=1,rate=1),'InvalidArgumentException'),
           (dict(descriptor,mode='produce',sequence=1,limit=1,rate=1,quota_policy=policy),'RuntimeException')]
    for payload,expected_exception in cases:
        result=subprocess.run(['php','-n',str(BASE/'emailing_action.php')],cwd=ROOT,env=env,input=json.dumps(payload).encode(),capture_output=True,timeout=5)
        assert not result.stderr
        facts=json.loads(result.stdout)
        if not payload:
            assert result.returncode==0 and facts=={'mode':'dry-run','sql_used':False,'broker_used':False}
        else:
            assert result.returncode==2 and facts['outcome']=='failed' and facts['phase']=='descriptor'
            assert facts['exception_class']==expected_exception
        checks+=1
finally:
    directory.rmdir()
    directory.parent.rmdir()
receipt={'checks':checks,'all_rejected_before_bootstrap_or_dry_run':True,'sql_executed':False,'broker_executed':False,'private_config_loaded':False}
(DATA_ROOT/'descriptor-check.public.json').write_text(json.dumps(receipt,indent=2)+'\n',encoding='utf-8')
print(json.dumps(receipt))
