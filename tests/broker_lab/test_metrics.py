"""Synthetic oracle tests only; these results never constitute load measurements."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
import unittest
import metrics as metrics

RUN='00000000-0000-4000-8000-000000000001'


def fixture():
    contract=metrics.plan(5,'daily100',1)
    a_producer=[];a_observer=[];a_publication=[];deliveries=[]
    for sequence in range(1,101):
        delivery='delivery-'+str(sequence)
        at=1000000000+sequence*100000000
        a_producer.extend([{'phase':'attempt','sequence':sequence,'begin_ns':at},
            {'phase':'accepted','sequence':sequence,'begin_ns':at,'committed_return_ns':at+10,
             'replayed':False,'request_id':'request-'+str(sequence),'delivery_ids':[delivery]}])
        logged=sequence<=10
        deliveries.append({'id':delivery,'generation':1 if logged else 2,'attempts':1 if logged else 0,
            'status':'logged' if logged else 'pending','diagnostic':None if logged else 'quota_wait',
            'available_at':None if logged else '2027-01-01T00:00:00.000000Z'})
        for stage,offset in [('reserved',25),('before_ack',100),('acked',120)]:
            a_observer.append({'worker':'a-'+str(sequence%2),'delivery_id':delivery,'generation':1,'stage':stage,'monotonic_ns':at+offset})
        for stage,offset in [('before_publish',20),('confirmed',40)]:
            a_publication.append({'event_id':'event-'+str(sequence),'delivery_id':delivery,'generation':1,'stage':stage,'monotonic_ns':at+offset,'envelope_bytes':240})
    a_producer.append({'mode':'produce','accepted':100})
    a_probe={'requests':100,'duplicate_delivery_messages':0,'status_counts':{'logged':10,'pending':90},'messages':10,
             'eligible_pending':0,'outbox_due':0,'future_pending':90,'quota_deferred':90,'unpaired_pending':0,'deliveries':deliveries}
    b_producer=[];b_observer=[];b_effects=[]
    for sequence in range(1,101):
        at=1000000000+sequence*100000000
        b_producer.extend([{'phase':'attempt','sequence':sequence,'published_ns':at},
            {'phase':'confirmed','sequence':sequence,'published_ns':at,'confirmed_ns':at+5,'bytes':2048}])
        b_observer.append({'worker':'b-'+str(sequence%3),'sequence':sequence,'created':True,'published_ns':at,'effect_recorded_ns':at+100})
        b_effects.append({'schema':1,'run_id':RUN,'sequence':sequence,'payload_sha256':metrics.protocol_hash(RUN,sequence,at,2048)})
    b_producer.append({'phase':'terminal','outcome':'completed','confirmed':100,'unconfirmed_attempts':0})
    barrier={'all_owned_workers_live':True,'consumers':{'a':2,'b':3},'observed_ns':1000000}
    settled={app:{'messages_ready':0,'messages_unacknowledged':0,'consumers':0} for app in ('a','b')}
    return [contract,RUN,a_producer,a_probe,a_observer,a_publication,b_producer,b_observer,b_effects,barrier,settled]


class AccountingTests(unittest.TestCase):
    def test_accepts_reconciled_quota_deferrals_and_real_completion_fraction(self):
        report=metrics.account_round(*fixture())
        self.assertEqual(report['a']['logged'],10)
        self.assertEqual(report['a']['pending_future_quota'],90)
        self.assertEqual(report['a']['completion_fraction'],.1)
        self.assertEqual(report['a']['total_including_quota_wait']['samples'],10)
        self.assertEqual(report['b']['unique_effects'],100)
        self.assertFalse(report['full_roadmap_acceptance'])

    def test_empty_queue_cannot_hide_missing_effect(self):
        args=fixture();args[8].pop()
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_unacknowledged_work_is_not_a_drained_queue(self):
        args=fixture();args[10]['b']['messages_unacknowledged']=1
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_false_is_not_an_observed_zero_message_count(self):
        args=fixture();args[10]['b']['messages_ready']=False
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_status_totals_cannot_disagree_with_individual_deliveries(self):
        args=fixture();args[3]['deliveries'][0].update(status='pending',diagnostic='quota_wait',available_at='2027-01-01T00:00:00Z')
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_future_work_requires_an_outbox_pair(self):
        args=fixture();args[3]['unpaired_pending']=1
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_deferred_work_requires_the_previous_generation_ack(self):
        args=fixture();args[4]=[row for row in args[4] if not(row['delivery_id']=='delivery-100' and row['stage']=='acked')]
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_duplicate_domain_effect_fails(self):
        args=fixture();args[3]['duplicate_delivery_messages']=1
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_missing_confirmation_fails(self):
        args=fixture();args[6]=[row for row in args[6] if not(row.get('phase')=='confirmed' and row['sequence']==42)]
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_confirmed_packet_does_not_override_a_content_conflict(self):
        args=fixture();args[8][0]['payload_sha256']='0'*64
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_unknown_worker_is_rejected(self):
        args=fixture();args[7][0]['worker']='foreign-worker'
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_publisher_cannot_run_before_both_worker_barriers(self):
        args=fixture();args[9]['observed_ns']=args[6][0]['published_ns']+1
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_protocol_publisher_also_obeys_the_barrier(self):
        args=fixture()
        shift=1099500000
        for row in args[6]:
            for key in ('published_ns','confirmed_ns'):
                if key in row:row[key]-=shift
        for row in args[7]:
            row['published_ns']-=shift;row['effect_recorded_ns']-=shift
        for effect in args[8]:
            packet=next(row for row in args[6] if row.get('phase')=='confirmed' and row['sequence']==effect['sequence'])
            effect['payload_sha256']=metrics.protocol_hash(RUN,effect['sequence'],packet['published_ns'],2048)
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_no_rate_catch_up_burst(self):
        args=fixture();args[2][3]['begin_ns']=args[2][1]['begin_ns']+1
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_publication_attempts_without_terminal_are_partial(self):
        args=fixture();args[6].pop()
        with self.assertRaises(ValueError):metrics.account_round(*args)

    def test_no_samples_does_not_mean_zero_latency(self):
        summary=metrics.summary_ns([])
        self.assertIsNone(summary['p50_ms'])
        self.assertIsNone(summary['max_ms'])

    def test_known_nearest_rank_values(self):
        report=metrics.summary_ns([100000000,1000000,10000000])
        self.assertEqual((report['p50_ms'],report['p95_ms'],report['p99_ms'],report['max_ms']),(10,100,100,100))

    def test_control_and_cross_load_keep_the_same_observation_window(self):
        for distribution in (5,10):
            cross=metrics.plan(distribution,'steady',1)
            control=metrics.plan(distribution,'steady',1,control=True)
            self.assertEqual(cross['observation_seconds'],control['observation_seconds'])
            self.assertEqual(cross['workers'],control['workers'])
            self.assertEqual(control['count_b'],0)
            self.assertFalse(control['quota_policy_may_be_changed'])

    def test_matrix_covers_every_profile_scenario_control_and_three_rounds(self):
        matrix=metrics.balanced_matrix()
        measured=[row for row in matrix if not row['warmup']]
        self.assertEqual(len(matrix),52)
        self.assertEqual(len(measured),48)
        expected={(distribution,scenario,number,control) for distribution in (5,10)
                  for scenario in ('daily100','daily200','steady','burst') for number in (1,2,3) for control in (False,True)}
        self.assertEqual({(row['distribution'],row['scenario'],row['round'],row['control']) for row in measured},expected)

    def test_warmups_are_separate_and_control_order_is_balanced(self):
        matrix=metrics.balanced_matrix()
        for distribution in (5,10):
            rows=[row for row in matrix if row['distribution']==distribution]
            self.assertTrue(all(row['warmup'] for row in rows[:2]))
            self.assertFalse(any(row['warmup'] for row in rows[2:]))
            for scenario in ('daily100','daily200','steady','burst'):
                scenarios=[row for row in rows if row['scenario']==scenario and not row['warmup']]
                self.assertEqual([row['control'] for row in scenarios],[False,True,True,False,False,True])


if __name__=='__main__':unittest.main()
