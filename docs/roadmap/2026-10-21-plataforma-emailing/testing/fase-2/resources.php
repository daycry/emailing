<?php
// UUID-scoped additive QA fixtures. No schema changes, refreshes or global deletes.
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailSetting;
use App\Models\MailSender;
use App\Models\Membership;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
require dirname(__DIR__, 5).'/vendor/autoload.php';
$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
[$script, $action, $run] = $argv;
if (!preg_match('/^[a-f0-9-]{36}$/', $run)) throw new RuntimeException('UUID required');
$file=__DIR__.'/raw/artifacts/'.$run.'.json';
$s=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
$ids=array_column($s['accounts'],'id');
if ($action==='setup') {
 foreach (['A'=>'log','B'=>'mailpit'] as $k=>$mode) {
  MailSetting::create(['account_id'=>$s['accounts'][$k]['id'],'mode'=>$mode,'smtp_host'=>'relay.invalid','smtp_port'=>587,'smtp_security'=>'starttls','smtp_username'=>'qa-user-'.$run,'smtp_password'=>'qa-secret-'.$run]);
  $sender=MailSender::create(['account_id'=>$s['accounts'][$k]['id'],'email'=>'sender-'.$run.'@example.test','name'=>'QA Sender','lab_authorized'=>true]);
  $s['senders'][$k]=$sender->id;
  $contact=Contact::create(['account_id'=>$s['accounts'][$k]['id'],'email'=>'duplicate-'.$run.'@example.test','name'=>'Original name','consent_status'=>'granted','consent_source'=>'Fixture evidence','consented_at'=>'2025-01-10 12:30:00']);
  $s['contacts'][$k]=$contact->id;
  $list=ContactList::create(['account_id'=>$s['accounts'][$k]['id'],'name'=>'QA List '.$k,'description'=>'Synthetic fixture']);
  $s['lists'][$k]=$list->id;
  $template=EmailTemplate::create(['account_id'=>$s['accounts'][$k]['id'],'name'=>'QA Fixture '.$k,'subject'=>'Hello {{ contact.name }}','html'=>'<p>Hello {{ contact.name }}</p>','text'=>'Hello {{ contact.name }}']);
  $s['templates'][$k]=$template->id;
 }
 file_put_contents($file,json_encode($s,JSON_PRETTY_PRINT));
 echo 'resources ready'; exit;
}
if($action==='revoke-read') {
 Membership::findOrFail($s['members']['marketer'])->update(['scopes'=>['contacts.write']]);
 echo 'read removed, write retained'; exit;
}
if($action==='restore-read') {
 Membership::findOrFail($s['members']['marketer'])->update(['scopes'=>['contacts.read','contacts.write']]);
 echo 'contact scopes restored'; exit;
}
if($action==='set-mode') {
 $mode=$argv[3]; if(!in_array($mode,['log','mailpit']))throw new RuntimeException('Invalid QA mode');
 MailSetting::where('account_id',$s['accounts']['A']['id'])->update(['mode'=>$mode]); echo 'mode changed'; exit;
}
if($action==='status') {
 $result=[];
 foreach(['contacts','contact_lists','contact_list_memberships','contact_imports','email_templates','mail_messages','audit_events'] as $table) {
  $rows=DB::table($table)->whereIn('account_id',$ids)->get();
  foreach($rows as $row) { unset($row->source_path,$row->artifact_path); }
  $result[$table]=$rows;
 }
 $setting=MailSetting::where('account_id',$s['accounts']['A']['id'])->first();
 $raw=DB::table('mail_settings')->where('account_id',$s['accounts']['A']['id'])->first();
 $result['credentialsEncrypted']=$raw->smtp_password!==$setting->smtp_password && $raw->smtp_username!==$setting->smtp_username;
 $result['mode']=$setting->mode;
    $result['mail_settings_sha256']=hash('sha256', DB::table('mail_settings')->whereIn('account_id',[$s['accounts']['A']['id'],$s['accounts']['B']['id']])->orderBy('id')->get()->toJson(JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
 echo json_encode($result); exit;
}
if($action==='precleanup') {
 foreach(DB::table('contact_imports')->whereIn('account_id',$ids)->pluck('source_path') as $p)Storage::disk('contact-imports')->delete($p);
 foreach(DB::table('mail_messages')->whereIn('account_id',$ids)->pluck('artifact_path') as $p)if($p)Storage::disk('mail-artifacts')->delete($p);
 echo 'own private artifacts removed'; exit;
}
throw new RuntimeException('Unknown QA resource action');
