from pathlib import Path
import subprocess,json,os
root=Path(__file__).resolve().parents[5]
out=Path(__file__).resolve().parent
plugin=Path.home()/'.codex/plugins/cache/daycry/custom-agents/1.22.0'
base=root/'docs/roadmap/2026-10-21-plataforma-emailing'
(out/'raw/artifacts').mkdir(parents=True,exist_ok=True)
(out/'screenshots').mkdir(exist_ok=True)
jobs={
 'ledger-lint':['python',str(plugin/'agent-kits/shared/ledger-lint.py'),str(base/'tasks.md')],
 'coverage-check':['python',str(plugin/'agent-kits/qa/coverage-check.py'),str(base/'tasks.md'),str(base/'test-plan.md'),str(base/'spec.md')],
 'guardrail':['C:/Program Files/Git/bin/bash.exe','-c',f'. "{plugin.as_posix()}/agent-kits/qa/lib-guardrail.sh"; guardrail_assert "http://127.0.0.1:8082" && guardrail_assert "http://127.0.0.1:8083"'],
 'knowledge':['python',str(plugin/'agent-kits/shared/knowledge-find.py'),'--tipo','gotcha','--contexto','Plataforma interna de emailing']
}
codes={}
for name,args in jobs.items():
 with (out/f'raw/{name}.txt').open('wb') as f:
  p=subprocess.run(args,cwd=root,stdout=f,stderr=subprocess.STDOUT)
 codes[name]=p.returncode
(out/'raw/entry-gates.json').write_text(json.dumps(codes,indent=2))
print(json.dumps(codes))
