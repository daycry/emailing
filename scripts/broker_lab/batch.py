"""Sequential complete matrix, isolated rounds, no implicit retry or partial acceptance."""
from pathlib import Path
from types import SimpleNamespace
import argparse
import hashlib
import importlib.util
import json
import os
import subprocess
import sys
import time
import uuid

from metrics import balanced_matrix,require
from runtime import BASE,DATA_ROOT,ROOT,Runtime,RESERVED,save,FLAGS,GATE

spec=importlib.util.spec_from_file_location('t19_round_controller',BASE/'load.py')
controller=importlib.util.module_from_spec(spec)
spec.loader.exec_module(controller)
from profile_process import _WindowsJob


def sources():
    names=[*controller.SOURCE_NAMES,'batch.py']
    return {name:hashlib.sha256((BASE/name).read_bytes()).hexdigest() for name in names}


def complete_matrix_result(result):
    matrix=balanced_matrix()
    return (isinstance(result,dict) and result.get('matrix_verdict')=='PASS'
            and result.get('planned_rounds')==result.get('completed_rounds')==len(matrix)
            and result.get('warmup_rounds')==sum(row['warmup'] for row in matrix)
            and result.get('measured_rounds')==sum(not row['warmup'] for row in matrix)
            and result.get('sources_stable') is True and result.get('cleanup_verified') is True
            and result.get('full_roadmap_acceptance') is False)


def reconcile_active(out):
    path=out/'active-round.public.json'
    if not path.is_file():return True
    active=json.loads(path.read_bytes())
    require(active.get('absence_checked') is True,'round_attribution_invalid')
    run=active.get('run_id')
    require(type(run) is str and str(uuid.UUID(run))==run and uuid.UUID(run).version==4,'round_attribution_invalid')
    directory=DATA_ROOT/('t19-load-'+run)
    if not directory.is_dir():return True
    return controller.scoped_cleanup(Runtime(run,directory,prefix='batch-reconcile'),run)


def worker(batch,out,prerequisite,accepted):
    matrix=balanced_matrix()
    before=sources();runtime_before=controller.runtime_hashes()
    source_dir=out/'command-sources';source_dir.mkdir()
    for name in before:
        data=(BASE/name).read_bytes()
        require(hashlib.sha256(data).hexdigest()==before[name],'source_changed_during_snapshot')
        (source_dir/name).write_bytes(data)
    manifest={'batch_id':batch,'matrix':matrix,'sources':before,'runtime_sources':runtime_before,
              'prerequisite':accepted,'automatic_retries':0,'rounds_run_sequentially':True,
              'matrix_acceptance_only':'Restoration, real clients/Google, independent QA and manual gates remain separate.'}
    save(out/'manifest.public.json',manifest)
    manifest_hash=hashlib.sha256((out/'manifest.public.json').read_bytes()).hexdigest()
    completed=[];failure=None
    try:
        for index,contract in enumerate(matrix,1):
            require(before==sources() and runtime_before==controller.runtime_hashes(),'source_changed_between_rounds')
            require(hashlib.sha256((out/'manifest.public.json').read_bytes()).hexdigest()==manifest_hash,'batch_manifest_changed')
            run=str(uuid.uuid4())
            require(not (DATA_ROOT/('t19-load-'+run)).exists(),'existing_round_directory_refused')
            active={'index':index,'run_id':run,'absence_checked':True,'workload':contract,'state':'starting'}
            save(out/'active-round.public.json',active)
            print(json.dumps({'phase':'round_start','index':index,'total':len(matrix),'run_id':run,
                              'distribution':contract['distribution'],'scenario':contract['scenario'],
                              'warmup':contract['warmup'],'control':contract['control'],'round':contract['round']}),flush=True)
            args=SimpleNamespace(distribution=contract['distribution'],scenario=contract['scenario'],
                                 round=contract['round'] or 1,control=contract['control'],warmup=contract['warmup'],prerequisite=str(prerequisite))
            exit_code=controller.owned_round(args,accepted,run)
            directory=DATA_ROOT/('t19-load-'+run)
            result=json.loads((directory/'result.public.json').read_bytes()) if (directory/'result.public.json').is_file() else {}
            supervision=json.loads((directory/'supervision.public.json').read_bytes())
            passed=(exit_code==0 and result.get('round_verdict')=='PASS' and result.get('sources_stable') is True
                    and supervision.get('cleanup_verified') is True and supervision.get('terminal_exit')==0)
            row={'index':index,'run_id':run,'workload':contract,'exit':exit_code,'round_passed':passed,
                 'result_sha256':hashlib.sha256((directory/'result.public.json').read_bytes()).hexdigest() if result else None}
            completed.append(row);save(out/'completed-rounds.public.json',completed)
            save(out/'active-round.public.json',{**active,'state':'completed','round_passed':passed})
            print(json.dumps({'phase':'round_complete','index':index,'total':len(matrix),'run_id':run,'passed':passed}),flush=True)
            require(passed,'round_failed_matrix_stopped')
    except BaseException as error:
        failure={'failure_class':type(error).__name__}
        if type(error) is ValueError:failure['failure_code']=str(error)
    finally:
        try:cleaned=reconcile_active(out)
        except BaseException:cleaned=False
        try:stable=before==sources() and runtime_before==controller.runtime_hashes()
        except BaseException:stable=False
        verdict='PASS' if failure is None and len(completed)==len(matrix) and all(row['round_passed'] for row in completed) and cleaned and stable else 'FAIL'
        result={'phase':'batch_terminal','batch_id':batch,'matrix_verdict':verdict,'planned_rounds':len(matrix),
                'completed_rounds':len(completed),'warmup_rounds':sum(row['workload']['warmup'] for row in completed),
                'measured_rounds':sum(not row['workload']['warmup'] for row in completed),'cleanup_verified':cleaned,
                'sources_stable':stable,'manifest_sha256':manifest_hash,'failure':failure,
                'restore_acceptance':False,'google_acceptance':False,'independent_qa':False,'full_roadmap_acceptance':False}
        save(out/'result.public.json',result)
        print(json.dumps(result),flush=True)
    return 0 if verdict=='PASS' else 1


def supervise(batch,out,prerequisite):
    """Overall budget derives from all per-round bounds; individual deadlines stay unchanged."""
    command=[sys.executable,'-E','-s','-B',str(Path(__file__).resolve()),'--execute','--worker',
             '--batch-id',batch,'--prerequisite',str(prerequisite)]
    deadline_seconds=len(balanced_matrix())*(1800+450)
    child=job=None;reason='not_started';code=2;started=time.monotonic_ns();contained=False;tree_cleaned=True
    stdout_path=out/'worker.stdout.private.txt';stderr_path=out/'worker.stderr.private.txt'
    with stdout_path.open('xb') as stdout,stderr_path.open('xb') as stderr:
        try:
            job=_WindowsJob()
            child=subprocess.Popen(command,cwd=ROOT,stdin=subprocess.PIPE,stdout=stdout,stderr=stderr,
                                   close_fds=True,creationflags=FLAGS)
            if child.pid in RESERVED:
                child.stdin.close()
                raise RuntimeError('reserved_process_identity_refused')
            job.attach(child)
            contained=True
            save(out/'controller-start.public.json',{'controller_pid':os.getpid(),'owned_child_pid':child.pid,
                 'deadline_seconds':deadline_seconds,'budget_basis':'52 rounds, each worker1800s plus450s reconciliation allowance.',
                 'individual_test_deadlines_modified':False})
            child.stdin.write(GATE);child.stdin.close()
            print(json.dumps({'phase':'batch_start','batch_id':batch,'planned_rounds':len(balanced_matrix()),'deadline_seconds':deadline_seconds}),flush=True)
            until=time.monotonic()+deadline_seconds
            cursor=0;pending=b''
            while True:
                if stdout_path.stat().st_size+stderr_path.stat().st_size>32*1048576:
                    reason='owned_output_limit';break
                with stdout_path.open('rb') as handle:
                    handle.seek(cursor);block=handle.read(65536);cursor+=len(block)
                pending+=block
                while b'\n' in pending:
                    line,pending=pending.split(b'\n',1)
                    try:observation=json.loads(line)
                    except (ValueError,UnicodeDecodeError):continue
                    if not isinstance(observation,dict):continue
                    if observation.get('phase') in ('round_start','round_complete','batch_terminal'):
                        allowed={'phase','index','total','run_id','distribution','scenario','warmup','control','round','passed',
                                 'batch_id','matrix_verdict','planned_rounds','completed_rounds','warmup_rounds','measured_rounds'}
                        print(json.dumps({key:value for key,value in observation.items() if key in allowed}),flush=True)
                if child.poll() is not None:code=child.returncode;reason='terminal';break
                if time.monotonic()>=until:reason='batch_supervision_deadline';break
                time.sleep(.25)
        except BaseException:
            reason='supervision_failed';code=2
        finally:
            if child is not None and child.stdin is not None and not child.stdin.closed:
                try:child.stdin.close()
                except OSError:pass
            if job is not None:
                try:job.close()  # Entire owned child tree; never a host-wide process query.
                except BaseException:reason='containment_cleanup_failed';code=2;tree_cleaned=False
            if child is not None and child.pid not in RESERVED:
                try:child.wait(timeout=30)
                except subprocess.TimeoutExpired:child.kill();child.wait(timeout=5)
    try:cleaned=reconcile_active(out)
    except BaseException:cleaned=False
    try:verified=complete_matrix_result(json.loads((out/'result.public.json').read_bytes()))
    except (OSError,ValueError):verified=False
    result_code=code if reason=='terminal' and contained and tree_cleaned and cleaned and (code!=0 or verified) else 2
    save(out/'supervision.public.json',{'terminal_exit':code,'result_exit':result_code,'reason':reason,'owned_tree_containment':contained,
         'tree_cleanup_succeeded':tree_cleaned,'matrix_result_verified':verified,'cleanup_verified':cleaned,
         'elapsed_ns':time.monotonic_ns()-started,'individual_test_deadlines_modified':False})
    print(json.dumps({'phase':'batch_supervision_terminal','batch_id':batch,'terminal_exit':code,'result_exit':result_code,
                     'matrix_result_verified':verified,'reason':reason,'cleanup_verified':cleaned}),flush=True)
    return result_code


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--execute',action='store_true')
    parser.add_argument('--worker',action='store_true')
    parser.add_argument('--batch-id')
    parser.add_argument('--prerequisite')
    args=parser.parse_args()
    if not args.execute:
        matrix=balanced_matrix()
        print(json.dumps({'mode':'dry-run','network_used':False,'sql_used':False,'planned_rounds':len(matrix),
              'warmup_rounds':sum(row['warmup'] for row in matrix),'measurement_rounds':sum(not row['warmup'] for row in matrix),
              'automatic_retries':0,'sequential':True,'nominal_observation_seconds':sum(row['observation_seconds'] for row in matrix)}));return 0
    if args.worker and sys.stdin.buffer.readline(64)!=GATE:return 2
    accepted=controller.prerequisite(args.prerequisite)  # No allocation or external operation before this gate.
    prerequisite=Path(args.prerequisite).resolve()
    if args.worker:
        batch=args.batch_id
        require(type(batch) is str and str(uuid.UUID(batch))==batch and uuid.UUID(batch).version==4,'batch_identity_invalid')
        out=DATA_ROOT/('t19-batch-'+batch);require(out.is_dir(),'batch_output_missing')
        return worker(batch,out,prerequisite,accepted)
    batch=str(uuid.uuid4());out=DATA_ROOT/('t19-batch-'+batch);out.mkdir(parents=True)
    return supervise(batch,out,prerequisite)


if __name__=='__main__':
    try:raise SystemExit(main())
    except Exception as error:
        print(json.dumps({'outcome':'refused','failure_class':type(error).__name__,'matrix_acceptance':False}),flush=True)
        raise SystemExit(2)
