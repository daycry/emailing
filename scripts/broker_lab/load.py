"""Integrated laboratory load controller. Default is dry-run, with explicit prerequisites."""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import argparse
import hashlib
import json
import os
import re
import secrets
import sys
import time
import uuid

from metrics import account_round,plan,require
from resources import ResourceSampler
from runtime import BASE,DATA_ROOT,ROOT,Runtime,records,save,FLAGS,GATE,run_bounded
from inventory import sources as runtime_hashes

SOURCE_NAMES=['load.py','runtime.py','resources.py','metrics.py',
              'emailing_action.php','protocol_worker.php','protocol_publisher.php',
              'protocol_event.php','inventory.py']


def prerequisite(directory):
    require(isinstance(directory,(str,Path)) and bool(str(directory)),'regression_evidence_required')
    directory=Path(directory).resolve()
    require(directory.is_relative_to(ROOT/'storage') and directory.is_dir(),'invalid_prerequisite_directory')
    values={}
    for name in ('command-receipt.public.json','test-summary.json','source-manifest.json'):
        path=directory/name
        require(path.is_file() and not path.is_symlink() and path.stat().st_nlink==1 and path.stat().st_size<=1048576,'regression_not_terminal')
        values[name]=json.loads(path.read_bytes())
    receipt=values['command-receipt.public.json'];summary=values['test-summary.json']
    require(receipt.get('termination_reason')=='terminal' and receipt.get('terminal_exit')==0
            and receipt.get('source_bytes_stable') is True and receipt.get('controller_sources_stable') is True
            and receipt.get('owned_tree_containment') is True and receipt.get('tree_cleanup_succeeded') is True
            and receipt.get('daemon_cleanup_verified') is True
            and receipt.get('full_selection') is True,'regression_not_accepted')
    require(summary.get('exit')==0 and summary.get('cases',0)>0 and summary.get('failures')==summary.get('errors')==0
            and summary.get('guarded_real_testing_identity') is True and summary.get('full_selection') is True,'regression_not_accepted')
    manifest=values['source-manifest.json']
    require(isinstance(manifest,dict) and bool(manifest) and manifest==runtime_hashes(),'regression_current_sources_mismatch')
    return {'directory':directory.relative_to(ROOT).as_posix(),'cases':summary['cases'],'assertions':summary['assertions'],
            'skipped':summary['skipped'],'receipt_sha256':hashlib.sha256((directory/'command-receipt.public.json').read_bytes()).hexdigest()}


def common(run,password):
    return {'execute':True,'run_id':run,'password':password,'broker_port':5672,
            'directory':str(ROOT/'storage'/('broker-lab-'+run)/'a')}


def scoped_cleanup(runtime,run):
    """Only claims persisted before creation may be deleted, including after a timed-out child."""
    out=runtime.out
    claim=out/'registry.public.json'
    names=json.loads(claim.read_bytes()) if claim.is_file() else []
    expected=['lab.t19.'+run+'.'+app for app in ('a','b')]
    require(type(names) is list and len(names)==len(set(names)) and all(name in expected for name in names),'cleanup_registry_invalid')
    result={'sql_fixture_absent':True,'broker_namespaces_absent':True,'private_evidence_retained':True}
    directory=ROOT/'storage'/('broker-lab-'+run)/'a'
    if (directory/'sql-attribution.public.json').is_file():
        if expected[0] not in names:
            result['sql_fixture_absent']=False  # An old directory alone never grants cleanup authority.
        else:
            try:
                # Cleanup never uses AMQP; no broker password needs to be persisted for reconciliation.
                observed=runtime.php('sql-cleanup',dict(common(run,'0'*64),mode='cleanup'))
                result['sql_fixture_absent']=observed.get('owned_sql_absent') is True
            except BaseException:result['sql_fixture_absent']=False
    for name in names:
        app=name[-1]
        for kind in ('vhost','user'):
            try:
                if runtime.namespace_exists(app,kind):
                    runtime.cli('delete-'+app+'-'+kind,'delete_vhost' if kind=='vhost' else 'delete_user',name)
                if runtime.namespace_exists(app,kind):result['broker_namespaces_absent']=False
            except BaseException:result['broker_namespaces_absent']=False
    save(out/'cleanup.public.json',result)
    return result['sql_fixture_absent'] and result['broker_namespaces_absent']


def helper_hashes():
    return {name:hashlib.sha256((BASE/name).read_bytes()).hexdigest() for name in SOURCE_NAMES}


def quiet(probe,count):
    counts=probe.get('status_counts',{})
    return (probe.get('requests')==count and set(counts)<= {'logged','pending'}
            and sum(counts.values())==count and probe.get('eligible_pending')==probe.get('outbox_due')==0
            and probe.get('future_pending')==probe.get('quota_deferred')==counts.get('pending',0)
            and probe.get('unpaired_pending')==0)


def worker(run,out,contract,accepted_prerequisite):
    runtime=Runtime(run,out)
    directories={app:ROOT/'storage'/('broker-lab-'+run)/app for app in ('a','b')}
    for directory in directories.values():directory.mkdir(parents=True)
    passwords={app:secrets.token_hex(32) for app in ('a','b')}
    descriptor=common(run,passwords['a'])
    result={'run_id':run,'load_acceptance':False,'restore_acceptance':False,'google_acceptance':False}
    success=False;stopped=False;claims=[];stage='setup';sampler_pool=None;pending_sample=None
    before_helpers=helper_hashes();before_runtime=runtime_hashes()
    source_dir=out/'command-sources';source_dir.mkdir()
    for name in SOURCE_NAMES:(source_dir/name).write_bytes((BASE/name).read_bytes())
    save(out/'source-manifest.public.json',{'helpers':before_helpers,'runtime':before_runtime})
    try:
        for app in ('a','b'):
            name='lab.t19.'+run+'.'+app
            require(not runtime.namespace_exists(app,'vhost') and not runtime.namespace_exists(app,'user'),'namespace_collision')
            claims.append(name);save(out/'registry.public.json',claims)
            runtime.cli('create-vhost-'+app,'add_vhost',name)
            runtime.cli('create-user-'+app,'add_user',name,passwords[app])
            pattern='^'+re.escape(name)+r'\..*$'
            runtime.cli('permissions-'+app,'set_permissions','-p',name,name,pattern,pattern,pattern)
        schema=runtime.php('guarded-schema',dict(descriptor,mode='schema'),timeout=90)
        require(schema.get('schema_ready') is True and schema.get('business_tables_empty_before_schema') is True,'testing_schema_not_empty')
        initialized=runtime.php('initialize-fixture',dict(descriptor,mode='init'))
        quotas=initialized['quotas']
        descriptor=dict(descriptor,quota_policy=quotas)
        manifest={'run_id':run,'workload':contract,'quota_policy':quotas,'prerequisite':accepted_prerequisite,
                  'sources':before_helpers,'runtime_source_count':len(before_runtime),
                  'measurement_scope':'Native PHP workers against shared Docker RabbitMQ/MySQL; log transport only.',
                  'per_process_consumer_tags':'Application B tags are explicit; A cardinality is broker-observed with live processes and one-consumer source.'}
        save(out/'manifest.public.json',manifest)
        manifest_digest=hashlib.sha256((out/'manifest.public.json').read_bytes()).hexdigest()
        stage='worker_barrier'
        for index in range(contract['workers']['a']):
            label='a-'+str(index)
            runtime.spawn(label,'worker','emailing_action.php',dict(descriptor,mode='worker',worker=label,seconds=contract['worker_deadline_seconds']))
        for index in range(contract['workers']['b']):
            label='b-'+str(index)
            runtime.spawn(label,'worker','protocol_worker.php',{'run_id':run,'password':passwords['b'],'port':5672,
                'worker':label,'seconds':contract['worker_deadline_seconds'],'maximum':max(1,contract['count_b']),
                'directory':str(directories['b'])})
        barrier_deadline=time.monotonic()+60
        barrier=None
        while time.monotonic()<barrier_deadline:
            require(runtime.workers_live(),'worker_stopped_before_barrier')
            ready=True
            for actor in runtime.actors:
                if actor.kind!='worker':continue
                observations=actor.observations()
                if not observations:ready=False;continue
                first=observations[0]
                require(first.get('worker')==actor.label and first.get('run_id')==run,'worker_ready_identity_mismatch')
                require(first.get('phase')==('worker_starting' if actor.label.startswith('a-') else 'consumer_ready'),'worker_ready_phase_mismatch')
            if ready:
                queues={app:runtime.queues(app,allow_absent=True) for app in ('a','b')}
                require(all(queues[app] is None or queues[app]['consumers']<=contract['workers'][app] for app in ('a','b')),'unexpected_consumer_before_load')
                if all(queues[app] is not None and queues[app]['consumers']==contract['workers'][app] for app in ('a','b')):
                    require(runtime.workers_live(),'worker_stopped_during_barrier')
                    require(all(queues[app]['messages_ready']==queues[app]['messages_unacknowledged']==0 for app in ('a','b')),'unexpected_messages_before_load')
                    barrier={'all_owned_workers_live':True,'consumers':{app:queues[app]['consumers'] for app in ('a','b')},
                             'observed_ns':time.monotonic_ns(),'queues':queues}
                    break
            time.sleep(.1)
        require(barrier is not None,'worker_barrier_deadline')
        save(out/'barrier.public.json',barrier)
        sampler=ResourceSampler(runtime,contract['minimum_available_disk_bytes']);sampler.prepare()
        samples=[sampler.sample()]
        save(out/'resources.public.json',samples)
        sampler_pool=ThreadPoolExecutor(max_workers=1,thread_name_prefix='owned-resource-observer')
        stage='cross_load'
        require(hashlib.sha256((out/'manifest.public.json').read_bytes()).hexdigest()==manifest_digest,'manifest_changed_before_load')
        started=time.monotonic()
        producers=[runtime.spawn('producer-a','producer','emailing_action.php',
                   dict(descriptor,mode='produce',sequence=1,limit=contract['count_a'],rate=contract['rate_a']))]
        if contract['count_b']:
            producers.append(runtime.spawn('producer-b','producer','protocol_publisher.php',{'execute':True,'run_id':run,
                'password':passwords['b'],'port':5672,'count':contract['count_b'],'rate':contract['rate_b']}))
        finished=None;cut=None;next_sample=time.monotonic()+contract['resource_sample_seconds']
        while True:
            require(runtime.workers_live(),'worker_stopped_during_load')
            require(all(actor.child.poll() in (None,0) for actor in producers),'producer_failed')
            runtime.php('dispatch',dict(descriptor,mode='dispatch',limit=20),timeout=120)
            now=time.monotonic()
            if pending_sample is not None and pending_sample.done():
                samples.append(pending_sample.result());save(out/'resources.public.json',samples)
                pending_sample=None
            if now>=next_sample and pending_sample is None:
                pending_sample=sampler_pool.submit(sampler.sample)
                next_sample=now+contract['resource_sample_seconds']
            if all(actor.child.poll()==0 for actor in producers):
                finished=finished or time.monotonic()
                require(time.monotonic()-finished<=contract['eligible_drain_seconds'],'eligible_drain_deadline')
                if time.monotonic()-started>=contract['observation_seconds']:
                    queue_cut={app:runtime.queues(app) for app in ('a','b')}
                    if all(queue_cut[app]['messages_ready']==queue_cut[app]['messages_unacknowledged']==0 for app in ('a','b')):
                        probe=runtime.php('quiet-probe',dict(descriptor,mode='probe'))
                        effects=len(list(directories['b'].glob('[0-9]*.json')))
                        if quiet(probe,contract['count_a']) and effects==contract['count_b']:
                            cut=probe['observed_at'];save(out/'quiet-cut.public.json',{'queues':queue_cut,'sql':probe,'observed_ns':time.monotonic_ns()})
                            break
            time.sleep(.25)
        stage='settle'
        if pending_sample is not None:samples.append(pending_sample.result());pending_sample=None
        sampler_pool.shutdown(wait=True);sampler_pool=None
        samples.append(sampler.sample());save(out/'resources.public.json',samples)
        stopped=runtime.stop(directories.values())
        require(stopped,'owned_actor_did_not_stop_cleanly')
        settled={app:runtime.queues(app) for app in ('a','b')}
        probe=runtime.php('final-probe',dict(descriptor,mode='probe',cut_at=cut))
        require(probe['eligibility_cut_at']==cut and probe['quotas']==quotas,'observation_cut_or_policy_changed')
        save(out/'final-observation.public.json',{'queues':settled,'sql':probe})
        a_stream=next(actor for actor in producers if actor.label=='producer-a').observations()
        b_stream=next((actor.observations() for actor in producers if actor.label=='producer-b'),[])
        a_events=[row for path in sorted(directories['a'].glob('a-*-events.jsonl')) for row in records(path)]
        b_events=[row for path in sorted(directories['b'].glob('b-*-events.jsonl')) for row in records(path)]
        b_effects=[json.loads(path.read_bytes()) for path in sorted(directories['b'].glob('[0-9]*.json'))]
        result['measurement']=account_round(contract,run,a_stream,probe,a_events,records(directories['a']/'publication-events.jsonl'),
                                            b_stream,b_events,b_effects,barrier,settled)
        result['manifest_sha256']=manifest_digest
        result['observation_cut_at']=cut
        result['observed_seconds_before_stop']=time.monotonic()-started
        result['resource_samples']=len(samples)
        success=True
    except BaseException as error:
        result.update(failure_stage=stage,failure_class=type(error).__name__)
        if type(error) is ValueError:result['failure_code']=str(error)
    finally:
        if sampler_pool is not None:sampler_pool.shutdown(wait=True,cancel_futures=True)
        if not stopped:
            try:result['actors_stopped_cleanly']=runtime.stop(directories.values())
            except BaseException:result['actors_stopped_cleanly']=False
        if (directories['a']/'sql-attribution.public.json').is_file():
            try:save(out/'pre-cleanup-probe.public.json',runtime.php('pre-cleanup-probe',dict(descriptor,mode='probe')))
            except BaseException:result['pre_cleanup_probe_available']=False
        try:result['cleanup_verified']=scoped_cleanup(runtime,run)
        except BaseException as error:result.update(cleanup_verified=False,cleanup_failure_class=type(error).__name__)
        try:result['sources_stable']=before_helpers==helper_hashes() and before_runtime==runtime_hashes()
        except BaseException:result['sources_stable']=False
        result['round_verdict']='PASS' if success and result['cleanup_verified'] and result['sources_stable'] else 'FAIL'
        save(out/'result.public.json',result)
        print(json.dumps(result),flush=True)
    return 0 if result['round_verdict']=='PASS' else 1


def owned_round(args,accepted,run=None):
    require(prerequisite(args.prerequisite)==accepted,'prerequisite_changed_before_round')
    run=run or str(uuid.uuid4())
    require(str(uuid.UUID(run))==run and uuid.UUID(run).version==4,'run_identity_invalid')
    fixture=ROOT/'storage'/('broker-lab-'+run)
    require(not fixture.exists() and not fixture.is_symlink() and not fixture.is_junction(),'existing_fixture_directory_refused')
    out=DATA_ROOT/('t19-load-'+run);out.mkdir(parents=True)
    command=[sys.executable,'-E','-s','-B',str(Path(__file__).resolve()),'--execute','--worker','--run-id',run,
             '--distribution',str(args.distribution),'--scenario',args.scenario,'--round',str(args.round),'--prerequisite',str(Path(args.prerequisite).resolve())]
    if args.control:command.append('--control')
    if args.warmup:command.append('--warmup')
    terminal=None;cleanup=False
    try:
        terminal=run_bounded(command,cwd=ROOT,timeout=1800,max_output_bytes=1048576)
        (out/'terminal.stdout.private.txt').write_bytes(terminal.stdout)
        print(terminal.stdout.decode('utf-8'),end='',flush=True)
    finally:
        try:cleanup=scoped_cleanup(Runtime(run,out,prefix='reconcile'),run)
        except BaseException:cleanup=False
        save(out/'supervision.public.json',{'terminal_exit':terminal.exit_code if terminal else None,'owned_tree_containment':True,
             'cleanup_verified':cleanup,'prerequisite':accepted,'deadline_seconds':1800})
    return 0 if terminal and terminal.exit_code==0 and cleanup else 1


def main():
    parser=argparse.ArgumentParser()
    parser.add_argument('--execute',action='store_true')
    parser.add_argument('--worker',action='store_true')
    parser.add_argument('--run-id')
    parser.add_argument('--distribution',type=int,choices=[5,10],default=5)
    parser.add_argument('--scenario',choices=['daily100','daily200','steady','burst'],default='daily100')
    parser.add_argument('--round',type=int,choices=[1,2,3],default=1)
    parser.add_argument('--control',action='store_true')
    parser.add_argument('--warmup',action='store_true')
    parser.add_argument('--prerequisite')
    args=parser.parse_args()
    contract=plan(args.distribution,args.scenario,0 if args.warmup else args.round,args.control,args.warmup)
    if not args.execute:
        print(json.dumps({'mode':'dry-run','sql_used':False,'network_used':False,'plan':contract}));return 0
    if args.worker and sys.stdin.buffer.readline(64)!=GATE:return 2
    accepted=prerequisite(args.prerequisite)  # Before UUID allocation, resources, SQL, broker CLI, or any child.
    if args.worker:
        run=args.run_id
        require(str(uuid.UUID(run))==run and uuid.UUID(run).version==4,'run_identity_invalid')
        out=DATA_ROOT/('t19-load-'+run);require(out.is_dir(),'owned_output_missing')
        return worker(run,out,contract,accepted)
    return owned_round(args,accepted,args.run_id)


if __name__=='__main__':
    try:raise SystemExit(main())
    except (ValueError,RuntimeError) as error:
        code=str(error) if type(error) is ValueError else 'owned_runtime_refused'
        print(json.dumps({'outcome':'refused','code':code,'load_acceptance':False}),flush=True)
        raise SystemExit(2)
