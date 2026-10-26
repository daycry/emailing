"""Minimal official reporter adapter: exact E2E-08 title and three repetitions only."""
import difflib,hashlib,json,os,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2]
directory=Path(os.environ['MCP_EVIDENCE_DIRECTORY']).resolve()
assert directory.is_relative_to(root/'storage') and directory.is_dir()
source=root/'storage/mcp-google-phase3-fix1-root/t08-playwright-json.py'
original=source.read_text(encoding='utf8')
title='MCP E2E-08 business consent and reviewed campaigns share the web domain'
assert "test('"+title+"'" in (root/'tests/e2e/mcp-google.spec.mjs').read_text(encoding='utf8')
modified=original.replace("'--reporter=json',",repr('--grep='+title+'$')+", '--repeat-each=3', '--reporter=json',")
assert modified!=original
(directory/'official-playwright-helper.py').write_bytes(source.read_bytes())
(directory/'stability-adapter.diff').write_text(''.join(difflib.unified_diff(original.splitlines(True),modified.splitlines(True),fromfile='official-helper',tofile='exact-filter-repeat-adapter')),encoding='utf8')
(directory/'qa3-stability.py').write_bytes(Path(__file__).read_bytes())
(directory/'stability-input.json').write_text(json.dumps({'base_helper_sha256':hashlib.sha256(source.read_bytes()).hexdigest(),'executed_adapter_sha256':hashlib.sha256(modified.encode()).hexdigest(),'exact_title':title,'repeat_each':3,'only_changes':['exact grep','repeat-each=3'],'launcher_guardrails_config_unchanged':True},indent=2),encoding='utf8')
exec(compile(modified,str(source),'exec'),{'__file__':str(source),'__name__':'__main__'})
