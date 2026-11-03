"""Pure/synthetic controller tests. No SQL, broker calls, or process signals."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
from types import SimpleNamespace
from concurrent.futures import ThreadPoolExecutor
import importlib.util
import json
import subprocess
import unittest
from unittest.mock import patch,Mock
import uuid

from resources import bytes_quantity,pair
from runtime import BASE,DATA_ROOT,ROOT,Runtime,records,save,protected_process_ids

spec=importlib.util.spec_from_file_location('controller_under_test',BASE/'load.py')
controller=importlib.util.module_from_spec(spec)
spec.loader.exec_module(controller)


class GuardTests(unittest.TestCase):
    def setUp(self):
        self.run=str(uuid.uuid4())
        self.directory=DATA_ROOT/('t19-controller-unit-'+self.run)
        self.directory.mkdir(parents=True)
        self.source_inventory={'tests/Unit/SyntheticTest.php':'a'*64}
        inventory=patch.object(controller,'runtime_hashes',return_value=dict(self.source_inventory))
        inventory.start()
        self.addCleanup(inventory.stop)

    def tearDown(self):
        resolved=self.directory.resolve()
        self.assertEqual(resolved.parent,DATA_ROOT.resolve())
        self.assertTrue(resolved.name.startswith('t19-controller-unit-'))
        # Exact verified owned tree, removed entirely in Python without another shell.
        paths=sorted(resolved.rglob('*'),key=lambda path:len(path.parts),reverse=True)
        for path in paths:
            self.assertFalse(path.is_symlink())
            if path.is_file():path.unlink()
            else:path.rmdir()
        resolved.rmdir()

    def synthetic_receipts(self):
        receipt={'termination_reason':'terminal','terminal_exit':0,'source_bytes_stable':True,
                 'controller_sources_stable':True,'owned_tree_containment':True,'synthetic_unit_fixture':True,
                 'tree_cleanup_succeeded':True,'daemon_cleanup_verified':True,'full_selection':True}
        summary={'exit':0,'cases':1,'assertions':1,'failures':0,'errors':0,'skipped':0,
                 'guarded_real_testing_identity':True,'synthetic_unit_fixture':True,'full_selection':True}
        save(self.directory/'command-receipt.public.json',receipt)
        save(self.directory/'test-summary.json',summary)
        save(self.directory/'source-manifest.json',self.source_inventory)
        return receipt,summary

    def test_missing_terminal_receipt_refuses(self):
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_optional_protected_process_ids_are_validated_without_host_defaults(self):
        self.assertEqual(protected_process_ids(''),frozenset())
        self.assertEqual(protected_process_ids('101,202'),frozenset([101,202]))
        for value in ('0','-1','unknown','4294967296','1,,2'):
            with self.assertRaises(RuntimeError):protected_process_ids(value)

    def test_round_entrypoint_rechecks_release_before_allocating_a_run(self):
        options=SimpleNamespace(prerequisite=str(self.directory))
        with patch.object(controller.uuid,'uuid4',side_effect=AssertionError('Allocation preceded release guard.')):
            with self.assertRaises(ValueError):controller.owned_round(options,{})

    def test_synthetic_terminal_contract_can_be_parsed_without_running_it(self):
        self.synthetic_receipts()
        observed=controller.prerequisite(self.directory)
        self.assertEqual(observed['cases'],1)
        self.assertEqual(list(self.directory.glob('registry*')),[])

    def test_interrupted_regression_is_not_a_release_signal(self):
        receipt,_=self.synthetic_receipts()
        receipt['termination_reason']='supervision_deadline_exceeded'
        save(self.directory/'command-receipt.public.json',receipt)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_source_drift_is_not_a_release_signal(self):
        receipt,_=self.synthetic_receipts();receipt['source_bytes_stable']=False
        save(self.directory/'command-receipt.public.json',receipt)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_successful_old_regression_cannot_qualify_changed_current_sources(self):
        self.synthetic_receipts()
        with patch.object(controller,'runtime_hashes',return_value={'tests/Unit/SyntheticTest.php':'b'*64}):
            with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_missing_source_manifest_cannot_qualify_load(self):
        self.synthetic_receipts()
        (self.directory/'source-manifest.json').unlink()
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_missing_complete_selection_proof_cannot_qualify_load(self):
        receipt,_=self.synthetic_receipts()
        del receipt['full_selection']
        save(self.directory/'command-receipt.public.json',receipt)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_filtered_summary_cannot_qualify_load(self):
        _,summary=self.synthetic_receipts()
        summary['full_selection']=False
        save(self.directory/'test-summary.json',summary)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_failed_tree_cleanup_cannot_release_the_load_runner(self):
        receipt,_=self.synthetic_receipts();receipt['tree_cleanup_succeeded']=False
        save(self.directory/'command-receipt.public.json',receipt)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_missing_daemon_cleanup_proof_cannot_release_the_load_runner(self):
        receipt,_=self.synthetic_receipts();del receipt['daemon_cleanup_verified']
        save(self.directory/'command-receipt.public.json',receipt)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_a_test_failure_is_not_a_release_signal(self):
        _,summary=self.synthetic_receipts();summary['failures']=1
        save(self.directory/'test-summary.json',summary)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_non_testing_identity_is_not_a_release_signal(self):
        _,summary=self.synthetic_receipts();summary['guarded_real_testing_identity']=False
        save(self.directory/'test-summary.json',summary)
        with self.assertRaises(ValueError):controller.prerequisite(self.directory)

    def test_outside_storage_is_refused_before_receipt_access(self):
        with self.assertRaises(ValueError):controller.prerequisite(ROOT/'tests')

    def test_foreign_namespace_claim_refuses_without_any_cli_call(self):
        save(self.directory/'registry.public.json',['foreign-vhost'])
        fake=SimpleNamespace(out=self.directory)
        with self.assertRaises(ValueError):controller.scoped_cleanup(fake,self.run)

    def test_cleanup_uses_only_the_claimed_namespace_and_checks_absence(self):
        name='lab.t19.'+self.run+'.a'
        save(self.directory/'registry.public.json',[name])
        calls=[];present={'vhost':True,'user':True}
        def exists(app,kind):
            self.assertEqual(app,'a');calls.append(('probe',kind));return present[kind]
        def delete(label,command,target):
            self.assertEqual(target,name);kind='vhost' if command=='delete_vhost' else 'user'
            calls.append(('delete',kind));present[kind]=False
        fake=SimpleNamespace(out=self.directory,namespace_exists=exists,cli=delete)
        self.assertTrue(controller.scoped_cleanup(fake,self.run))
        self.assertEqual(calls,[('probe','vhost'),('delete','vhost'),('probe','vhost'),('probe','user'),('delete','user'),('probe','user')])

    def test_one_cleanup_error_does_not_skip_the_other_resource_kind(self):
        name='lab.t19.'+self.run+'.a';save(self.directory/'registry.public.json',[name]);calls=[]
        def exists(app,kind):
            calls.append(kind)
            if kind=='vhost':raise RuntimeError('synthetic_query_failure')
            return False
        fake=SimpleNamespace(out=self.directory,namespace_exists=exists)
        self.assertFalse(controller.scoped_cleanup(fake,self.run))
        self.assertIn('user',calls)

    def test_unclaimed_namespaces_are_never_probed_or_deleted(self):
        fake=SimpleNamespace(out=self.directory)
        self.assertTrue(controller.scoped_cleanup(fake,self.run))

    def test_existing_fixture_directory_is_not_reused_or_cleaned(self):
        fixture=self.directory/'storage'/('broker-lab-'+self.run)/'a'
        fixture.mkdir(parents=True);marker=fixture/'existing.marker';marker.write_text('existing')
        target=self.directory/'evidence'
        with patch.object(controller,'ROOT',self.directory),patch.object(controller,'DATA_ROOT',target),\
             patch.object(controller,'prerequisite',return_value={}),\
             patch.object(controller,'run_bounded',side_effect=AssertionError('Existing fixture was reused.')):
            options=SimpleNamespace(prerequisite='synthetic',distribution=5,scenario='daily100',round=1,control=False,warmup=False)
            with self.assertRaises(ValueError):controller.owned_round(options,{},self.run)
        self.assertTrue(marker.is_file())
        self.assertFalse(target.exists())

    def test_sql_cleanup_requires_a_recorded_namespace_claim(self):
        fixture=self.directory/'storage'/('broker-lab-'+self.run)/'a'
        fixture.mkdir(parents=True)
        (fixture/'sql-attribution.public.json').write_text('{"synthetic":true}')
        fake=SimpleNamespace(out=self.directory,php=Mock(side_effect=AssertionError('Unclaimed SQL cleanup.')))
        with patch.object(controller,'ROOT',self.directory):
            self.assertFalse(controller.scoped_cleanup(fake,self.run))
        fake.php.assert_not_called()

    def test_partial_live_stream_is_preserved_without_accepting_a_terminal(self):
        path=self.directory/'synthetic-stream.jsonl';path.write_bytes(b'{"phase":"attempt"}\n{"phase":')
        self.assertEqual(records(path,terminal=False),[{'phase':'attempt'}])
        with self.assertRaises(RuntimeError):records(path,terminal=True)

    def test_queue_boolean_is_not_a_numeric_zero(self):
        runtime=Runtime(self.run,self.directory)
        row={'name':'lab.t19.'+self.run+'.a.ready','messages_ready':False,'messages_unacknowledged':0,'consumers':0}
        runtime.cli=lambda *args:subprocess.CompletedProcess([],0,stdout=json.dumps([row]).encode())
        with self.assertRaises(RuntimeError):runtime.queues('a')

    def test_foreign_queue_name_is_refused(self):
        runtime=Runtime(self.run,self.directory)
        runtime.cli=lambda *args:subprocess.CompletedProcess([],0,stdout=b'[{"name":"foreign"}]')
        with self.assertRaises(RuntimeError):runtime.queues('a')

    def test_absent_queue_is_pending_only_during_the_startup_barrier(self):
        runtime=Runtime(self.run,self.directory)
        runtime.cli=lambda *args:subprocess.CompletedProcess([],0,stdout=b'[]')
        self.assertIsNone(runtime.queues('a',allow_absent=True))
        with self.assertRaises(RuntimeError):runtime.queues('a')

    def test_resource_units_and_zero_are_interpreted_explicitly(self):
        self.assertEqual(bytes_quantity('3MiB'),3145728)
        self.assertEqual(bytes_quantity('1kB'),1000)
        self.assertEqual(pair('0B / 1.25GiB'),[0,1342177280])
        for bad in ('-1MiB','unknown','NaNB','1requests'):
            with self.assertRaises(ValueError):bytes_quantity(bad)

    def test_atomic_receipt_replacement_leaves_no_temporary_artifact(self):
        path=self.directory/'receipt.public.json'
        save(path,{'phase':'first'});save(path,{'phase':'second'})
        self.assertEqual(json.loads(path.read_bytes()),{'phase':'second'})
        self.assertEqual([p.name for p in self.directory.iterdir()],['receipt.public.json'])

    def test_runtime_refuses_public_or_foreign_output_directories(self):
        with self.assertRaises(RuntimeError):Runtime(self.run,ROOT/'scripts')
        with self.assertRaises(RuntimeError):Runtime(self.run,BASE)
        with self.assertRaises(RuntimeError):Runtime('not-a-run',self.directory)
        with self.assertRaises(RuntimeError):Runtime(self.run,self.directory,prefix='../outside')

    def test_parallel_observation_and_dispatch_never_overwrite_receipts(self):
        runtime=Runtime(self.run,self.directory)
        result=subprocess.CompletedProcess([],0,stdout=b'{}\n',stderr=b'')
        with patch('runtime.subprocess.run',return_value=result):
            with ThreadPoolExecutor(max_workers=4) as pool:
                futures=[pool.submit(runtime.command,'synthetic',['synthetic-command']) for _ in range(20)]
                for future in futures:self.assertEqual(future.result().returncode,0)
        self.assertEqual(len(list(self.directory.glob('worker-*.receipt.public.json'))),20)
        self.assertEqual(len(list(self.directory.glob('worker-*.stdout.private.txt'))),20)
        self.assertEqual(len(list(self.directory.glob('worker-*.stderr.private.txt'))),20)
        self.assertEqual(runtime.sequence,20)

    def test_command_timeout_keeps_the_partial_response_and_a_failed_receipt(self):
        runtime=Runtime(self.run,self.directory)
        error=subprocess.TimeoutExpired(['synthetic-command'],1,output=b'partial-synthetic-response',stderr=b'synthetic-timeout')
        with patch('runtime.subprocess.run',side_effect=error):
            with self.assertRaises(RuntimeError):runtime.command('synthetic',['synthetic-command'])
        self.assertEqual(next(self.directory.glob('*.stdout.private.txt')).read_bytes(),b'partial-synthetic-response')
        receipt=json.loads(next(self.directory.glob('*.receipt.public.json')).read_bytes())
        self.assertIsNone(receipt['exit'])
        self.assertEqual(receipt['terminal_reason'],'command_deadline')


if __name__=='__main__':unittest.main()
