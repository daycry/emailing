"""Run the existing six E2Es with Playwright's original JSON reporter artifact."""

import hashlib
import json
import os
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[2]
directory = Path(os.environ['MCP_EVIDENCE_DIRECTORY']).resolve()
assert directory.is_relative_to(root / 'storage')
assert directory.is_dir()
result_file = directory / 'playwright-results.json'
assert not result_file.exists()
env = os.environ.copy()
env['PLAYWRIGHT_BROWSERS_PATH'] = str(root / 'storage/mcp-google-phase2/playwright-browsers')
env['PLAYWRIGHT_JSON_OUTPUT_FILE'] = str(result_file)
env['PLAYWRIGHT_LAST_RUN_OUTPUT_FILE'] = str(directory / 'playwright-last-run.json')
command = ['cmd.exe', '/d', '/c', 'npx.cmd', '--no-install', 'playwright', 'test', 'tests/e2e/mcp-google.spec.mjs', '--reporter=json', '--output=' + str(directory / 'browser-results')]
result = subprocess.run(command, cwd=root, env=env)
receipt = {'command_exit_code': result.returncode, 'original_reporter_file_exists': result_file.exists(), 'synthetic_report': False, 'source_or_config_mutated': False}
valid = False
if result_file.exists():
    raw = result_file.read_bytes()
    data = json.loads(raw)
    valid = isinstance(data.get('config'), dict) and isinstance(data.get('suites'), list) and isinstance(data.get('stats'), dict)
    receipt.update({'json_shape_valid': valid, 'original_reporter_sha256': hashlib.sha256(raw).hexdigest(), 'stats': data.get('stats')})
(directory / 'playwright-json-receipt.json').write_text(json.dumps(receipt, indent=2) + '\n', encoding='utf-8')
raise SystemExit(result.returncode if result.returncode != 0 else (0 if valid else 2))
