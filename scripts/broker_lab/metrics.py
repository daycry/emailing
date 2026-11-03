"""Pure workload contracts and accounting. No configuration, filesystem or network IO."""
import hashlib
import json
import math


def require(condition, code):
    if not condition:raise ValueError(code)


def integer(value):
    return type(value) is int and value>=0


def plan(distribution, scenario, round_number, control=False, warmup=False):
    require(type(distribution) is int and distribution in (5,10),'invalid_distribution')
    require(type(control) is bool and type(warmup) is bool,'invalid_phase')
    scenarios={'daily100':(100,100,10),'daily200':(200,200,10),'steady':(200,1000,10),'burst':(500,2000,25)}
    require(type(scenario) is str and scenario in scenarios,'invalid_scenario')
    require(type(round_number) is int and round_number in ([0] if warmup else [1,2,3]),'invalid_round')
    a,b,rate=(10,50,10) if warmup else scenarios[scenario]
    return {'distribution':distribution,'scenario':scenario,'round':round_number,'control':control,'warmup':warmup,
            'workers':{'a':2 if distribution==5 else 4,'b':3 if distribution==5 else 6},
            'count_a':a,'count_b':0 if control else b,'rate_a':rate,'rate_b':rate,
            'observation_seconds':math.ceil(max(a/rate,b/rate)),
            'eligible_drain_seconds':300,'reconnect_seconds':60,'resource_sample_seconds':5,
            'worker_deadline_seconds':1800,'minimum_available_disk_bytes':256*1048576,
            'a_private_body_text_bytes':2048,'b_wire_body_bytes':2048,
            'a_wire_envelope':'actual domain JSON; measure its bytes, no padding',
            'http_admission_exercised':False,'quota_policy_may_be_changed':False}


def balanced_matrix():
    """Warm-up is separate; reverse control order in round two without changing workloads."""
    result=[]
    for distribution in (5,10):
        result.extend(plan(distribution,'steady',0,control=control,warmup=True) for control in (False,True))
        for scenario in ('daily100','daily200','steady','burst'):
            for number in (1,2,3):
                result.extend(plan(distribution,scenario,number,control=control)
                              for control in ((False,True) if number%2 else (True,False)))
    return result


def summary_ns(values):
    require(all(integer(value) for value in values),'invalid_duration')
    if not values:return {'samples':0,'method':'nearest_rank','p50_ms':None,'p95_ms':None,'p99_ms':None,'max_ms':None}
    rows=sorted(values)
    result={'samples':len(rows),'method':'nearest_rank'}
    for label,p in [('p50_ms',.5),('p95_ms',.95),('p99_ms',.99)]:
        result[label]=rows[max(0,math.ceil(len(rows)*p)-1)]/1000000
    result['max_ms']=rows[-1]/1000000
    return result


def selected(stream, phase):
    return [row for row in stream if row.get('phase')==phase]


def ordered_packets(stream, phase, count, timestamp, rate):
    rows=selected(stream,phase)
    require(len(rows)==count and [row.get('sequence') for row in rows]==list(range(1,count+1)),'packet_accounting_mismatch')
    require(all(integer(row.get(timestamp)) for row in rows),'invalid_packet_clock')
    starts=[row[timestamp] for row in rows]
    require(all(right-left>=math.ceil(1000000000/rate) for left,right in zip(starts,starts[1:])),'producer_rate_exceeded')
    return rows


def protocol_hash(run, sequence, published_ns, size):
    fields={'schema':1,'run_id':run,'sequence':sequence,'published_ns':published_ns,'padding':''}
    empty=json.dumps(fields,separators=(',',':')).encode('ascii')
    require(size>=len(empty),'invalid_protocol_size')
    fields['padding']='x'*(size-len(empty))
    body=json.dumps(fields,separators=(',',':')).encode('ascii')
    require(len(body)==size,'invalid_protocol_size')
    return hashlib.sha256(body).hexdigest()


def account_round(contract,run,a_producer,a_probe,a_observer,a_publication,b_producer,b_observer,b_effects,barrier,settled):
    """A quiet queue alone cannot satisfy this contract: durable effects and every ID must match."""
    count_a,count_b=contract['count_a'],contract['count_b']
    require(barrier.get('all_owned_workers_live') is True,'worker_barrier_not_live')
    for app in ('a','b'):
        observed=barrier.get('consumers',{}).get(app)
        require(integer(observed) and observed==contract['workers'][app],'worker_barrier_cardinality')
        queue=settled.get(app,{})
        require(all(integer(queue.get(key)) and queue[key]==0 for key in ('messages_ready','messages_unacknowledged','consumers')),'broker_not_settled')
    accepted=ordered_packets(a_producer,'accepted',count_a,'begin_ns',contract['rate_a'])
    attempts=selected(a_producer,'attempt')
    require([row.get('sequence') for row in attempts]==list(range(1,count_a+1)),'acceptance_attempts_incomplete')
    require(all(row.get('replayed') is False and isinstance(row.get('delivery_ids'),list) and len(row['delivery_ids'])==1 for row in accepted),'invalid_acceptance_receipt')
    by_delivery={row['delivery_ids'][0]:row for row in accepted}
    require(len(by_delivery)==count_a,'duplicate_delivery_receipt')
    require(len({row.get('request_id') for row in accepted})==count_a,'duplicate_request_receipt')
    require(a_producer[-1].get('mode')=='produce' and a_producer[-1].get('accepted')==count_a,'acceptance_terminal_missing')
    require(all(integer(a_probe.get(key)) for key in ('requests','duplicate_delivery_messages','messages','eligible_pending','outbox_due','future_pending','quota_deferred','unpaired_pending')),'invalid_domain_count')
    require(a_probe.get('requests')==count_a and a_probe.get('duplicate_delivery_messages')==0,'domain_accounting_mismatch')
    counts=a_probe.get('status_counts',{})
    require(all(key in ('logged','pending') and integer(value) for key,value in counts.items()),'unexpected_delivery_outcome')
    logged,pending=counts.get('logged',0),counts.get('pending',0)
    require(logged+pending==count_a and a_probe.get('messages')==logged,'domain_accounting_mismatch')
    require(a_probe.get('eligible_pending')==a_probe.get('outbox_due')==0,'eligible_work_not_drained')
    require(a_probe.get('future_pending')==a_probe.get('quota_deferred')==pending,'pending_work_not_reconciled')
    require(a_probe['unpaired_pending']==0,'pending_outbox_missing')
    deliveries=a_probe.get('deliveries',[])
    require(len(deliveries)==count_a and {row.get('id') for row in deliveries}==set(by_delivery),'domain_delivery_ids_mismatch')
    require(sum(row.get('status')=='logged' for row in deliveries)==logged
            and sum(row.get('status')=='pending' for row in deliveries)==pending,'delivery_totals_inconsistent')
    stages={}
    for row in a_observer:
        require(row.get('worker') in {'a-'+str(index) for index in range(contract['workers']['a'])}
                and row.get('delivery_id') in by_delivery and integer(row.get('monotonic_ns')),'foreign_worker_observation')
        stages.setdefault((row['delivery_id'],row['generation'],row['stage']),[]).append(row['monotonic_ns'])
    publication={}
    for row in a_publication:
        require(row.get('delivery_id') in by_delivery and integer(row.get('monotonic_ns')),'foreign_publication_observation')
        require(row.get('stage') not in ('nack','return'),'publication_rejected')
        publication.setdefault((row['delivery_id'],row['generation'],row['stage']),[]).append(row['monotonic_ns'])
    total_latency,queue_latency,work_latency,confirm_latency=[],[],[],[]
    for row in deliveries:
        require(row.get('status') in ('logged','pending'),'delivery_state_mismatch')
        key=(row['id'],row['generation'])
        if row['status']=='logged':
            require(row.get('attempts')==1,'unexpected_transport_retry')
            values=[]
            for stage in ('reserved','before_ack','acked'):
                events=stages.get((*key,stage),[])
                require(len(events)==1,'missing_or_duplicate_worker_stage')
                values.append(events[0])
            before=publication.get((*key,'before_publish'),[])
            confirmed=publication.get((*key,'confirmed'),[])
            require(len(before)==len(confirmed)==1,'publication_confirmation_missing')
            origin=by_delivery[row['id']]['begin_ns']
            require(origin<=before[0]<=values[0]<=values[1]<=values[2] and confirmed[0]>=before[0],'invalid_event_order')
            total_latency.append(values[2]-origin)
            queue_latency.append(values[0]-before[0])
            work_latency.append(values[1]-values[0])
            confirm_latency.append(confirmed[0]-before[0])
        else:
            require(row.get('diagnostic')=='quota_wait' and row.get('available_at') is not None,'pending_work_not_reconciled')
            require(integer(row.get('generation')) and row['generation']>=2,'pending_generation_not_advanced')
            previous=(row['id'],row['generation']-1)
            require(len(stages.get((*previous,'acked'),[]))==1
                    and len(publication.get((*previous,'confirmed'),[]))==1,'pending_previous_generation_unsettled')
    confirmed_b=ordered_packets(b_producer,'confirmed',count_b,'published_ns',contract['rate_b'])
    require([row.get('sequence') for row in selected(b_producer,'attempt')]==list(range(1,count_b+1)),'protocol_attempts_incomplete')
    if count_b:
        require(b_producer[-1].get('phase')=='terminal' and b_producer[-1].get('outcome')=='completed'
                and b_producer[-1].get('confirmed')==count_b and b_producer[-1].get('unconfirmed_attempts')==0,'protocol_terminal_missing')
    else:require(not b_producer and not b_observer and not b_effects,'control_has_foreign_load')
    require(len(b_effects)==count_b and {row.get('sequence') for row in b_effects}==set(range(1,count_b+1)),'protocol_effects_incomplete')
    by_sequence={row['sequence']:row for row in confirmed_b}
    for row in b_effects:
        published=by_sequence[row['sequence']]
        require(row.get('run_id')==run and row.get('payload_sha256')==protocol_hash(run,row['sequence'],published['published_ns'],contract['b_wire_body_bytes']),'protocol_effect_content_mismatch')
    creations={}
    b_latencies=[]
    for row in b_observer:
        require(row.get('worker') in {'b-'+str(index) for index in range(contract['workers']['b'])}
                and row.get('sequence') in by_sequence and type(row.get('created')) is bool,'foreign_protocol_observation')
        packet=by_sequence[row['sequence']]
        require(row.get('published_ns')==packet['published_ns'] and integer(row.get('effect_recorded_ns')) and row['effect_recorded_ns']>=packet['published_ns'],'invalid_protocol_clock')
        if row['created']:
            creations[row['sequence']]=creations.get(row['sequence'],0)+1
            b_latencies.append(row['effect_recorded_ns']-packet['published_ns'])
    require(creations==dict.fromkeys(range(1,count_b+1),1),'protocol_duplicate_or_missing_effect')
    require(all(packet.get('bytes')==contract['b_wire_body_bytes'] for packet in confirmed_b),'protocol_size_mismatch')
    require(barrier.get('observed_ns',0)>0 and all(row['begin_ns']>barrier['observed_ns'] for row in accepted),'publication_before_readiness')
    require(all(row['published_ns']>barrier['observed_ns'] for row in confirmed_b),'protocol_publication_before_readiness')
    return {'a':{'accepted':count_a,'logged':logged,'pending_future_quota':pending,
                 'completion_fraction':logged/count_a,'total_including_quota_wait':summary_ns(total_latency),
                 'publish_to_reserved':summary_ns(queue_latency),'reserved_to_durable_outcome':summary_ns(work_latency),
                 'publisher_confirmation':summary_ns(confirm_latency)},
            'b':{'confirmed':count_b,'unique_effects':count_b,'duplicates_prevented':len(b_observer)-count_b,
                 'published_to_effect':summary_ns(b_latencies)},'accounting':'complete','full_roadmap_acceptance':False}
