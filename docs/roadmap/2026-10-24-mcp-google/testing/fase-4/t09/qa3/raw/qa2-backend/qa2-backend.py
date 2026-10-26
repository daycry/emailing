"""QA2 independent eight-case correction regression; guarded local testing DB."""
import hashlib,json,os,subprocess
from pathlib import Path
R=Path(__file__).resolve().parents[2]
assert os.environ.get('APP_ENV')=='testing'
assert os.environ.get('DB_DATABASE')==os.environ.get('DB_USERNAME')=='emailing_testing'
assert os.environ.get('DB_URL')==''
d=Path(os.environ['MCP_EVIDENCE_DIRECTORY']).resolve()
assert d.is_relative_to(R/'storage') and d.is_dir()
os.environ['T16_EVIDENCE_DIRECTORY']=str(d)
source=Path(__file__).read_bytes();(d/'qa2-backend.py').write_bytes(source)
f='MigrationReplayTest|test_historical_starts_without_reconstructible_links_and_real_zero_have_a_neutral_page_empty_state|test_real_sdk_modern_discovery_legacy_initialization_and_live_revocation'
cmd=['php','vendor/bin/phpunit','--filter='+f,'--log-junit',str(d/'backend-junit.xml')]
(d/'backend-input.json').write_text(json.dumps({'command':cmd,'helper_sha256':hashlib.sha256(source).hexdigest(),'expected_cases':8,'database_guard':'Tests/TestCase and TestingDatabaseGuard verify DATABASE()/CURRENT_USER() before migrations','private_configuration_opened_by_wrapper':False,'external_smtp_enabled':False,'t16_directory_is_owned_run':True},indent=2),encoding='utf8')
raise SystemExit(subprocess.run(cmd,cwd=R).returncode)
