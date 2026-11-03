"""Cross-runtime canonical envelope proof, no application bootstrap or network."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
import json
import subprocess
import metrics as metrics

ROOT=Path(__file__).resolve().parents[2]
BASE=ROOT/'scripts/broker_lab'
DATA_ROOT=ROOT/'storage/broker-lab-evidence'
DATA_ROOT.mkdir(parents=True,exist_ok=True)
code="require $argv[1]; echo hash('sha256', Tests\\BrokerLab\\ProtocolEvent::encode($argv[2],(int)$argv[3],(int)$argv[4],(int)$argv[5]));"
run='00000000-0000-4000-8000-000000000001'
cases=[(1,1,2048),(2000,123456789123456,2048),(10000,9223372036854775000,4096)]
for sequence,published,size in cases:
    result=subprocess.run(['php','-n','-r',code,'--',str(BASE/'protocol_event.php'),run,str(sequence),str(published),str(size)],cwd=ROOT,capture_output=True,timeout=5)
    assert result.returncode==0 and not result.stderr
    assert result.stdout.decode('ascii')==metrics.protocol_hash(run,sequence,published,size)
receipt={'cross_runtime_cases':len(cases),'canonical_sha256_matches':True,'sql_used':False,'broker_used':False,'private_config_loaded':False}
(DATA_ROOT/'protocol-parity.public.json').write_text(json.dumps(receipt,indent=2)+'\n',encoding='utf-8')
print(json.dumps(receipt))
