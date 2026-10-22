from pathlib import Path
import json,subprocess,sys
out=Path(__file__).resolve().parent
root=out.parents[4]
run=sys.argv[1]
f=json.loads((out/f'raw/artifacts/{run}.json').read_text())
ids=[a['id'] for a in f['accounts'].values()]
code=r'''
require '/var/www/emailing/vendor/autoload.php';
$app=require '/var/www/emailing/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Support\TestingDatabaseGuard::assertSafe();
$ids=json_decode($argv[1],true);$run=$argv[2];
$owned=Illuminate\Support\Facades\DB::table('accounts')->join('users','users.id','=','accounts.owner_id')->whereIn('accounts.id',$ids)->where('users.email','like','qa-'.$run.'-%@example.test')->count();
if($owned!==count($ids))throw new RuntimeException('QA account ownership mismatch');
foreach(['contact-imports'=>['contact_imports','source_path'],'mail-artifacts'=>['mail_messages','artifact_path']] as $disk=>[$table,$column]){
 $storage=Illuminate\Support\Facades\Storage::disk($disk);
 $root='/var/www/emailing/storage/app/private/'.$disk.'/';
 foreach(Illuminate\Support\Facades\DB::table($table)->whereIn('account_id',$ids)->pluck($column) as $p){
  if(!$p)continue;
  $path=$storage->path($p);
  $ownedShape=$disk==='contact-imports' ? preg_match('~^sources/[a-f0-9-]{36}\\.csv$~D',$p) : (preg_match('~^[0-9]+/[a-f0-9-]{36}\\.eml$~D',$p) && in_array((int)explode('/',$p)[0],$ids,true));
  if(!str_starts_with($path,$root)||str_contains($p,'..')||!$ownedShape)throw new RuntimeException('QA artifact path mismatch');
  $storage->delete($p);
 }
}
echo 'Owned Docker QA artifacts removed'.PHP_EOL;
'''
args=['docker','compose','-p','emailing-qa','-f','compose.yaml','-f','tests/.runtime/compose.qa.yaml','exec','-T','emailing-php','php','-r',code,json.dumps(ids),run]
with (out/f'raw/artifacts/{run}-docker-cleanup.txt').open('wb') as log:
 p=subprocess.run(args,cwd=root,stdout=log,stderr=subprocess.STDOUT,creationflags=subprocess.CREATE_NO_WINDOW)
if p.returncode:raise SystemExit(p.returncode)
print('Owned Docker QA artifacts removed')
