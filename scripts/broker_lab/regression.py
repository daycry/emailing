"""Supervise the complete native Unit+Feature suite; output remains under storage."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import subprocess
import sys
import time
import uuid
import xml.etree.ElementTree as ET

from inventory import sources
from docker_scope import DockerScope
from metrics import require
from runtime import BASE,DATA_ROOT,ROOT,Runtime,RESERVED,FLAGS,GATE,save
from profile_process import _WindowsJob

GUARD=r"""require 'vendor/autoload.php'; try {
$app=require 'bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\App\Support\TestingDatabaseGuard::assertSafe();
$r=\Illuminate\Support\Facades\DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()');
if((int)$r->n!==0 || \Illuminate\Support\Facades\DB::transactionLevel()!==0){exit(2);}
echo 'exclusive_testing_guard_passed';
}catch(\Throwable){echo 'testing_guard_refused';exit(2);}"""

PHP_GATE=r"""if (fgets(STDIN,64)!=="profile-execute-v1\n") { exit(2); }
$entry=$argv[1]; array_shift($argv); $argc=count($argv);
$_SERVER['argv']=$argv; $_SERVER['argc']=$argc;
$_SERVER['SCRIPT_FILENAME']=$entry; $_SERVER['SCRIPT_NAME']=$entry; $_SERVER['PHP_SELF']=$entry;
require $entry;"""


def selection():
    files=[]
    for directory in ('tests/Unit','tests/Feature'):
        for path in (ROOT/directory).rglob('*Test.php'):
            require(path.is_file() and not path.is_symlink() and path.resolve().is_relative_to(ROOT/directory),'test_selection_refused')
            files.append(path.relative_to(ROOT).as_posix())
    return sorted(set(files))


def gated_php_command(entry,arguments):
    return ['php','-d','zend.exception_ignore_args=1','-r',PHP_GATE,'--',str(entry),*arguments]


def junit_summary(path,exit_code):
    require(path.is_file() and not path.is_symlink() and 0<path.stat().st_size<=64*1048576,'junit_missing_or_incomplete')
    cases=list(ET.parse(path).getroot().iter('testcase'))
    require(bool(cases),'junit_empty')
    ids=[case.get('class','')+'::'+case.get('name','') for case in cases]
    require(len(ids)==len(set(ids)),'junit_duplicate_case')
    assertions=[case.get('assertions','0') for case in cases]
    require(all(value.isascii() and value.isdecimal() and len(value)<=12 for value in assertions),'junit_invalid_assertion_count')
    return {'exit':exit_code,'cases':len(cases),'assertions':sum(int(value) for value in assertions),
            'failures':sum(case.find('failure') is not None for case in cases),
            'errors':sum(case.find('error') is not None for case in cases),
            'skipped':sum(case.find('skipped') is not None for case in cases),'ids':ids,
            'problem_cases':[{'id':ids[index],'kind':event.tag} for index,case in enumerate(cases)
                             for event in case if event.tag in ('failure','error')],
            'summary_valid':True,'junit_sha256':hashlib.sha256(path.read_bytes()).hexdigest()}


def worker(run,out):
    runtime=Runtime(run,out,prefix='regression')
    runtime.env['BROKER_LAB_REGRESSION_RUN_ID']=run
    guarded=False;summary={'exit':2,'cases':0,'assertions':0,'failures':0,'errors':0,'skipped':0,'summary_valid':False}
    stage='platform';status=2
    try:
        code="echo json_encode(['php'=>PHP_VERSION,'sockets'=>extension_loaded('sockets'),'pdo_mysql'=>extension_loaded('pdo_mysql'),'openssl'=>extension_loaded('openssl'),'memory_limit'=>ini_get('memory_limit')]);"
        observed=runtime.command('php-platform',['php','-r',code],timeout=10)
        platform=json.loads(observed.stdout)
        save(out/'platform.public.json',platform)
        require(all(platform.get(name) is True for name in ('sockets','pdo_mysql','openssl')),'required_php_extension_missing')
        stage='database_guard'
        observed=runtime.command('exclusive-database-guard',['php','-d','zend.exception_ignore_args=1','-r',GUARD],timeout=30)
        require(observed.stdout==b'exclusive_testing_guard_passed','exclusive_testing_database_required')
        guarded=True;stage='phpunit'
        files=json.loads((out/'selection.json').read_bytes())
        require(files==selection() and bool(files),'full_test_selection_changed')
        command=gated_php_command(ROOT/'vendor/bin/phpunit',['--colors=never','--log-junit',str(out/'junit.xml'),*files])
        with (out/'phpunit.stdout.private.txt').open('xb') as stdout,(out/'phpunit.stderr.private.txt').open('xb') as stderr:
            child=subprocess.Popen(command,cwd=ROOT,env=runtime.env,stdin=subprocess.PIPE,stdout=stdout,stderr=stderr,creationflags=FLAGS)
            if child.pid in RESERVED:
                child.stdin.close()
                raise ValueError('protected_process_identity_refused')
            try:child.stdin.write(GATE)
            finally:child.stdin.close()
            exit_code=child.wait()
        stage='junit'
        summary=junit_summary(out/'junit.xml',exit_code)
        status=exit_code if summary['failures']==summary['errors']==0 else 1
    except Exception as error:
        summary.update(failure_stage=stage,failure_class=type(error).__name__)
        if type(error) is ValueError:summary['failure_code']=str(error)
    finally:
        summary.update(guarded_real_testing_identity=guarded,private_configuration_dumped=False,
                       full_selection=True,filter=None,test_files_selected=len(selection()))
        save(out/'test-summary.json',summary)
        print(json.dumps({key:value for key,value in summary.items() if key not in ('ids','problem_cases')}),flush=True)
    return status


def supervise(run,out,deadline):
    before=sources();helpers={name:hashlib.sha256((BASE/name).read_bytes()).hexdigest() for name in ('regression.py','inventory.py','runtime.py','docker_scope.py','docker_scope.php')}
    save(out/'source-manifest.json',before);save(out/'controller-sources.public.json',helpers)
    save(out/'selection.json',selection())
    command=[sys.executable,'-E','-s','-B',str(Path(__file__).resolve()),'--execute','--worker','--run-id',run,'--deadline',str(deadline)]
    child=job=None;reason='not_started';status=2;contained=False;cleaned=True;daemon_cleaned=False;started=time.monotonic_ns()
    scope=DockerScope(Runtime(run,out,prefix='regression-daemon'),run)
    with (out/'worker.stdout.private.txt').open('xb') as stdout,(out/'worker.stderr.private.txt').open('xb') as stderr:
        try:
            scope.preflight()  # Scoped label absence before any suite child or SQL.
            job=_WindowsJob()
            child=subprocess.Popen(command,cwd=ROOT,stdin=subprocess.PIPE,stdout=stdout,stderr=stderr,close_fds=True,creationflags=FLAGS)
            require(child.pid not in RESERVED,'protected_process_identity_refused')
            job.attach(child);contained=True
            save(out/'controller-start.public.json',{'controller_pid':os.getpid(),'owned_child_pid':child.pid,'deadline_seconds':deadline,
                 'test_level_deadlines_modified':False,'test_files':len(selection())})
            child.stdin.write(GATE);child.stdin.close()
            print(json.dumps({'phase':'regression_started','directory':out.relative_to(ROOT).as_posix(),'selected_files':len(selection()),
                              'supervision_deadline_seconds':deadline,'test_level_deadlines_modified':False}),flush=True)
            until=time.monotonic()+deadline
            while True:
                if child.poll() is not None:status=child.returncode;reason='terminal';break
                if time.monotonic()>=until:reason='supervision_deadline_exceeded';break
                if any(path.is_file() and path.stat().st_size>64*1048576 for path in out.glob('*.private.txt')):
                    reason='owned_output_limit';break
                time.sleep(.25)
        except BaseException:
            reason='supervision_failed';status=2
        finally:
            if child is not None and not child.stdin.closed:
                try:child.stdin.close()
                except OSError:pass
            if job is not None:
                try:job.close()
                except BaseException:cleaned=False
            if child is not None and child.pid not in RESERVED:
                try:child.wait(timeout=30)
                except subprocess.TimeoutExpired:child.kill();child.wait(timeout=5);cleaned=False
            try:daemon_cleaned=scope.cleanup()
            except BaseException:daemon_cleaned=False
    after=sources()
    helper_stable=all(hashlib.sha256((BASE/name).read_bytes()).hexdigest()==value for name,value in helpers.items())
    try:
        summary=json.loads((out/'test-summary.json').read_bytes())
        valid=(summary.get('summary_valid') is True and summary.get('full_selection') is True
               and summary.get('guarded_real_testing_identity') is True and summary.get('exit')==0
               and summary.get('cases',0)>0 and summary.get('failures')==summary.get('errors')==0)
    except (OSError,ValueError):valid=False
    receipt={'terminal_exit':status,'termination_reason':reason,'source_bytes_stable':before==after,
             'controller_sources_stable':helper_stable,'sources':len(before),'elapsed_ns':time.monotonic_ns()-started,
             'owned_tree_containment':contained,'native_tree_cleanup_succeeded':cleaned,'daemon_cleanup_verified':daemon_cleaned,
             'tree_cleanup_succeeded':cleaned and daemon_cleaned,'test_summary_verified':valid,
             'test_level_deadlines_modified':False,'full_selection':True,'restore_acceptance':False,'google_acceptance':False}
    save(out/'command-receipt.public.json',receipt)
    print(json.dumps(receipt),flush=True)
    return 0 if status==0 and reason=='terminal' and contained and cleaned and daemon_cleaned and before==after and helper_stable and valid else 2


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--execute',action='store_true')
    parser.add_argument('--worker',action='store_true')
    parser.add_argument('--run-id')
    parser.add_argument('--deadline',type=int,default=28800)
    args=parser.parse_args()
    require(3600<=args.deadline<=86400,'invalid_whole_suite_deadline')
    if not args.execute:
        print(json.dumps({'mode':'dry-run','sql_used':False,'test_files':len(selection()),'filter':None,
                          'supervision_deadline_seconds':args.deadline,'test_level_deadlines_modified':False}));return 0
    if args.worker and sys.stdin.buffer.readline(64)!=GATE:return 2
    require(os.name=='nt','native_windows_required')
    require((ROOT/'vendor/bin/phpunit').is_file() and (ROOT/'vendor/autoload.php').is_file(),'composer_dependencies_required')
    if args.worker:
        run=args.run_id
        require(type(run) is str and str(uuid.UUID(run))==run and uuid.UUID(run).version==4,'run_identity_invalid')
        out=DATA_ROOT/('t19-regression-'+run);require(out.is_dir(),'owned_output_missing')
        return worker(run,out)
    run=str(uuid.uuid4());out=DATA_ROOT/('t19-regression-'+run);out.mkdir(parents=True)
    return supervise(run,out,args.deadline)


if __name__=='__main__':
    try:raise SystemExit(main())
    except Exception as error:
        print(json.dumps({'outcome':'refused','failure_class':type(error).__name__,'baseline_accepted':False}),flush=True)
        raise SystemExit(2)
