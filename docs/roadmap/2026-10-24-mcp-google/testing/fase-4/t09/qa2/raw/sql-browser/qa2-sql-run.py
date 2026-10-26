import hashlib,json,os,subprocess,sys
from pathlib import Path
R=Path(__file__).resolve().parents[2];C=Path(__file__).parent
assert os.environ.get('APP_ENV')=='testing' and os.environ.get('DB_DATABASE')==os.environ.get('DB_USERNAME')=='emailing_testing' and os.environ.get('DB_URL')==''
label=sys.argv[1];assert label in ['browser','stability']
attribution=json.loads((C/(label+'-attribution.json')).read_text(encoding='utf8'))
d=Path(os.environ['MCP_EVIDENCE_DIRECTORY']).resolve();assert d.is_relative_to(R/'storage') and d.is_dir()
php=C/'sql-browser-check.php';original=R/'storage/mcp-google-t09-root/sql-browser-check.php'
assert php.read_bytes()==original.read_bytes()
(d/'sql-browser-check.php').write_bytes(php.read_bytes());(d/'qa2-sql-run.py').write_bytes(Path(__file__).read_bytes())
cmd=['php',str(php),str(R/attribution['directory'])]
(d/'sql-input.json').write_text(json.dumps({'command':cmd,'php_helper_sha256':hashlib.sha256(php.read_bytes()).hexdigest(),'source_unchanged_from_reviewed_pattern':True,'owned_fixture_directory':attribution['directory'],'id_values_not_embedded':True,'data_mutated':False},indent=2),encoding='utf8')
raise SystemExit(subprocess.run(cmd,cwd=R).returncode)
