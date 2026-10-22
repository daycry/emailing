<?php
// QA-only exact upload registry. Runtime paths come from Laravel/Livewire.
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

[$script, $profile, $action, $run] = $argv;
if (!in_array($profile, ['native','docker'], true) || !preg_match('/^[a-f0-9-]{36}$/D', $run)) throw new RuntimeException('Invalid QA identity');
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
if ($profile === 'native') $app->loadEnvironmentFrom('.env.qa');
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode($argv[4] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$disk = FileUploadConfiguration::disk();
$directory = FileUploadConfiguration::directory();
if (config('filesystems.disks.'.$disk.'.driver') !== 'local') throw new RuntimeException('QA requires local temporary disk');
$storage = Storage::disk($disk);
function normalized(string $path): string { return rtrim(str_replace('\\', '/', $path), '/'); }
function within(string $path, string $root): bool {
    $path=normalized($path);$root=normalized($root).'/';
    return PHP_OS_FAMILY==='Windows' ? str_starts_with(strtolower($path),strtolower($root)) : str_starts_with($path,$root);
}
$diskRoot = realpath($storage->path(''));
$tempRoot = realpath($storage->path($directory));
if (!$diskRoot || !$tempRoot || !within($tempRoot,$diskRoot)) throw new RuntimeException('Configured temporary directory is unsafe or absent');
$tempRoot=normalized($tempRoot);
function proof(array $entry): string { unset($entry['proof']);return hash_hmac('sha256', json_encode($entry, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR), app('encrypter')->getKey()); }
function safeName(string $name): void { if ($name==='' || basename($name)!==$name || str_contains($name,'..') || preg_match('~[\\\\/:\x00]~',$name)) throw new RuntimeException('Unsafe temporary filename'); }
function exactPath(string $root,string $name): string {
    safeName($name);$path=$root.'/'.$name;
    if(is_link($path))throw new RuntimeException('Temporary symlink rejected');
    if(file_exists($path)&&(!is_file($path)||!within((string)realpath($path),$root)))throw new RuntimeException('Temporary canonical path rejected');
    return $path;
}
function validatedEntry(array $entry): array {
    global $run,$profile,$disk,$directory,$tempRoot;
    if(($entry['run']??null)!==$run || ($entry['profile']??null)!==$profile || ($entry['disk']??null)!==$disk || ($entry['directory']??null)!==$directory || ($entry['root']??null)!==$tempRoot)throw new RuntimeException('Upload registry identity/config mismatch');
    foreach($entry['files']??[] as $file)exactPath($tempRoot,(string)$file['name']);
    if(!isset($entry['proof'])||!hash_equals(proof($entry),$entry['proof']))throw new RuntimeException('Upload registry proof mismatch');
    $handle=$entry['handle']??'';
    if(!str_starts_with($handle,'livewire-file:'))throw new RuntimeException('Unsupported temporary handle');
    $name=TemporaryUploadedFile::extractPathFromSignedPath(substr($handle,14));
    if($name===false || $name!==$entry['filename'])throw new RuntimeException('Temporary signed handle mismatch');
    safeName($name);
    if($entry['captured_response_handle']??null){$response=TemporaryUploadedFile::extractPathFromSignedPath(substr($entry['captured_response_handle'],14));if($response!==$name&&$response!=='/'.$name)throw new RuntimeException('Captured upload response differs from canonical handle');}
    foreach($entry['files'] as $file)if(!in_array($file['name'],[$name,$name.'.json'],true))throw new RuntimeException('Unrelated registry file rejected');
    return $entry;
}
if($action==='describe') {
    echo json_encode(['profile'=>$profile,'disk'=>$disk,'directory'=>$directory,'root'=>$tempRoot,'existing_files_count'=>count(array_filter(glob($tempRoot.'/*')?:[],'is_file'))]);exit;
}
if($action==='decode-observe') {
    $handle=$input['handle']??'';$signed=str_starts_with($handle,'livewire-file:')?substr($handle,14):$handle;
    $decoded=TemporaryUploadedFile::extractPathFromSignedPath($signed);
    echo json_encode(['signature_valid'=>$decoded!==false,'decoded'=>$decoded,'basename'=>$decoded===false?null:basename($decoded)]);exit;
}
if($action==='path-guard-observe') {
    validatedEntry($input['entry']??[]);$name=$input['name']??'';
    if($name!=='b03-controlled-link-'.$run)throw new RuntimeException('Controlled link identity rejected');
    try{exactPath($tempRoot,$name);echo json_encode(['rejected'=>false]);}catch(RuntimeException $error){echo json_encode(['rejected'=>true,'reason'=>$error->getMessage()]);}exit;
}
if($action==='record') {
    $handle=$input['handle']??'';
    if(!str_starts_with($handle,'livewire-file:'))throw new RuntimeException('Expected one signed Livewire file');
    $name=TemporaryUploadedFile::extractPathFromSignedPath(substr($handle,14));
    if($name===false)throw new RuntimeException('Invalid temporary signature');
    $responseHandle=null;
    if($input['response_handle']??false){$responseHandle=$handle;if(str_starts_with($name,'/'))$name=substr($name,1);safeName($name);$handle='livewire-file:'.TemporaryUploadedFile::signPath($name);}
    safeName($name);$path=exactPath($tempRoot,$name);
    $userId=(int)($input['user_id']??0);$accountId=(int)($input['account_id']??0);
    $ownedUser=DB::table('users')->where('id',$userId)->where('email','like','qa-'.$run.'-%@example.test')->exists();
    $ownedAccount=DB::table('accounts')->join('users','users.id','=','accounts.owner_id')->where('accounts.id',$accountId)->where('users.email','like','qa-'.$run.'-%@example.test')->exists();
    $membership=DB::table('memberships')->where('user_id',$userId)->where('account_id',$accountId)->exists();
    if(!$ownedUser||!$ownedAccount||!$membership)throw new RuntimeException('QA actor/account ownership rejected');
    $file=TemporaryUploadedFile::createFromLivewire($name);$original=$file->getClientOriginalName();
    if($original!==($input['original_name']??null)||!str_contains($original,$run)||!preg_match('/^[a-f0-9]{64}$/D',$input['session_hash']??''))throw new RuntimeException('QA upload attribution rejected');
    if(!is_file($path)||hash_file('sha256',$path)!==($input['sha256']??null)||!str_contains(file_get_contents($path),$run))throw new RuntimeException('QA upload content proof rejected');
    $files=[];
    foreach([$name,$name.'.json'] as $candidate){$p=exactPath($tempRoot,$candidate);if(is_file($p))$files[]=['name'=>$candidate,'sha256'=>hash_file('sha256',$p),'bytes'=>filesize($p)];}
    $entry=['version'=>1,'run'=>$run,'profile'=>$profile,'user_id'=>$userId,'account_id'=>$accountId,'session_hash'=>$input['session_hash'],'handle'=>$handle,'captured_response_handle'=>$responseHandle,'filename'=>$name,'original_name'=>$original,'disk'=>$disk,'directory'=>$directory,'root'=>$tempRoot,'files'=>$files];
    $entry['proof']=proof($entry);echo json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
if($action==='sentinel-create') {
    $id=$input['id']??'';if(!preg_match('/^[a-f0-9-]{36}$/D',$id)||$id===$run)throw new RuntimeException('Controlled foreign UUID required');
    $name='b03-controlled-foreign-'.$id.'.csv';$path=exactPath($tempRoot,$name);
    if(file_exists($path))throw new RuntimeException('Controlled sentinel already exists');
    file_put_contents($path,'Controlled foreign '.$id);
    $sentinel=['type'=>'controlled-sentinel','run'=>$run,'profile'=>$profile,'disk'=>$disk,'directory'=>$directory,'root'=>$tempRoot,'filename'=>$name,'sha256'=>hash_file('sha256',$path)];
    $sentinel['proof']=proof($sentinel);echo json_encode($sentinel,JSON_UNESCAPED_SLASHES);exit;
}
if($action==='sentinel-remove'||$action==='sentinel-inspect') {
    if(($input['type']??null)!=='controlled-sentinel'||($input['run']??null)!==$run||($input['profile']??null)!==$profile||($input['root']??null)!==$tempRoot||!hash_equals(proof($input),$input['proof']??''))throw new RuntimeException('Controlled sentinel ownership rejected');
    $path=exactPath($tempRoot,$input['filename']);
    if(!is_file($path)||hash_file('sha256',$path)!==$input['sha256'])throw new RuntimeException('Controlled sentinel changed');
    if($action==='sentinel-inspect'){echo json_encode(['controlled_sentinel_preserved'=>true]);exit;}
    unlink($path);echo json_encode(['controlled_sentinel_removed'=>!file_exists($path)]);exit;
}
if($action==='guard-probe') {
    $entry=validatedEntry($input);$checks=[];
    foreach(['traversal','foreign_account','foreign_user','foreign_run'] as $case){
        $candidate=$entry;
        if($case==='traversal'){$candidate['files'][0]['name']='../outside.csv';$candidate['proof']=proof($candidate);}
        if($case==='foreign_account')$candidate['account_id']++;
        if($case==='foreign_user')$candidate['user_id']++;
        if($case==='foreign_run')$candidate['run']='00000000-0000-4000-8000-000000000000';
        try{validatedEntry($candidate);$checks[$case]=false;}catch(RuntimeException){$checks[$case]=true;}
    }
    $path=exactPath($tempRoot,$entry['files'][0]['name']);
    if(PHP_OS_FAMILY==='Windows'){$checks['outside_symlink']='external_fixture_required';$checks['own_upload_preserved']=is_file($path)&&hash_file('sha256',$path)===$entry['files'][0]['sha256'];echo json_encode($checks);exit;}
    $backup=$path.'.b03-backup';$outside=$storage->path('b03-controlled-outside-'.$run.'.csv');
    if(file_exists($backup)||file_exists($outside))throw new RuntimeException('Guard probe path already exists');
    file_put_contents($outside,'Controlled outside '.$run);$outsideHash=hash_file('sha256',$outside);$linkCreated=false;
    try{
        rename($path,$backup);$linkCreated=@symlink($outside,$path);
        if($linkCreated){try{validatedEntry($entry);$checks['outside_symlink']=false;}catch(RuntimeException $error){$checks['outside_symlink']=in_array($error->getMessage(),['Temporary symlink rejected','Temporary canonical path rejected'],true);}$checks['outside_link_type']='file_symlink';}
        else $checks['outside_symlink']='platform_unavailable';
    }finally{
        if($linkCreated&&is_link($path))unlink($path);
        if(file_exists($backup)){if(file_exists($path))throw new RuntimeException('Refusing to replace unexpected guard probe file');rename($backup,$path);}
        if(!is_file($outside)||hash_file('sha256',$outside)!==$outsideHash)throw new RuntimeException('Controlled outside file changed');
        unlink($outside);
    }
    $checks['own_upload_preserved']=is_file($path)&&hash_file('sha256',$path)===$entry['files'][0]['sha256'];
    echo json_encode($checks);exit;
}
$entry=validatedEntry($input);
if($action==='inspect'||$action==='legacy-clean') {
    $legacy=normalized(storage_path('app/private/livewire-tmp'));
    // Bounded reproduction: old location, only this exact registered filename.
    if($action==='legacy-clean')foreach($entry['files'] as $file){$candidate=$legacy.'/'.$file['name'];if(is_file($candidate)&&!is_link($candidate)&&within((string)realpath($candidate),$legacy)&&hash_file('sha256',$candidate)===$file['sha256'])unlink($candidate);}
    echo json_encode(['profile'=>$profile,'configured_disk'=>$disk,'configured_directory'=>$directory,'legacy_directory_differs'=>$legacy!==$tempRoot,'exists'=>array_map(fn($f)=>is_file(exactPath($tempRoot,$f['name'])),$entry['files']),'imports'=>DB::table('contact_imports')->where('account_id',$entry['account_id'])->count()]);exit;
}
if($action==='cleanup') {
    // Validate every exact path and digest before deleting any file.
    foreach($entry['files'] as $file){$path=exactPath($tempRoot,$file['name']);if(is_file($path)&&hash_file('sha256',$path)!==$file['sha256'])throw new RuntimeException('Registered temporary content changed');}
    $deleted=0;$absent=0;
    foreach($entry['files'] as $file){$path=exactPath($tempRoot,$file['name']);if(is_file($path)){unlink($path);$deleted++;}else $absent++;}
    $remaining=array_map(fn($file)=>file_exists(exactPath($tempRoot,$file['name'])),$entry['files']);
    if(array_filter($remaining))throw new RuntimeException('Owned temporary file remains');
    echo json_encode(['profile'=>$profile,'disk'=>$disk,'directory'=>$directory,'deleted'=>$deleted,'already_absent'=>$absent,'registered_files_remaining'=>0]);exit;
}
throw new RuntimeException('Unknown temporary-upload QA action');
