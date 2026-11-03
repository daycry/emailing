"""Batch lifecycle tests with synthetic receipts and mocked execution; not load proof."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
from contextlib import redirect_stdout
from io import StringIO,BytesIO
from types import SimpleNamespace
import importlib.util
import json
import unittest
from unittest.mock import patch

import test_guards as fixtures
from metrics import balanced_matrix
from runtime import BASE,DATA_ROOT,save

spec=importlib.util.spec_from_file_location('batch_under_test',BASE/'batch.py')
batch=importlib.util.module_from_spec(spec);spec.loader.exec_module(batch)


class BatchTests(unittest.TestCase):
    def setUp(self):
        fixtures.GuardTests.setUp(self)
        self.round_directories=[]

    def tearDown(self):
        for directory in self.round_directories:
            self.assertEqual(directory.resolve().parent,DATA_ROOT.resolve())
            self.assertTrue(directory.name.startswith('t19-load-'))
            for path in directory.iterdir():
                self.assertTrue(path.is_file());self.assertFalse(path.is_symlink());path.unlink()
            directory.rmdir()
        fixtures.GuardTests.tearDown(self)

    def synthetic_round(self,args,accepted,run,*,passed=True):
        directory=DATA_ROOT/('t19-load-'+run);directory.mkdir();self.round_directories.append(directory)
        save(directory/'result.public.json',{'round_verdict':'PASS' if passed else 'FAIL','sources_stable':True,'synthetic_unit_fixture':True})
        save(directory/'supervision.public.json',{'terminal_exit':0 if passed else 1,'cleanup_verified':True,'synthetic_unit_fixture':True})
        return 0 if passed else 1

    def run_worker(self,round_action,*,source_side_effect=None):
        matrix=balanced_matrix()[:2]
        changes=patch.object(batch,'sources',side_effect=source_side_effect) if source_side_effect else patch.object(batch,'sources',return_value={})
        with changes,patch.object(batch,'balanced_matrix',return_value=matrix),patch.object(batch.controller,'runtime_hashes',return_value={}),\
             patch.object(batch.controller,'owned_round',side_effect=round_action) as invoke,\
             patch.object(batch,'reconcile_active',return_value=True) as reconcile,redirect_stdout(StringIO()):
            code=batch.worker(self.run,self.directory,self.directory,{'synthetic_unit_fixture':True})
        return code,invoke.call_count,reconcile.call_count,json.loads((self.directory/'result.public.json').read_bytes())

    def test_first_failed_round_stops_the_matrix_without_a_retry(self):
        code,calls,reconciles,result=self.run_worker(lambda args,accepted,run:self.synthetic_round(args,accepted,run,passed=False))
        self.assertEqual((code,calls,reconciles),(1,1,1))
        self.assertEqual(result['matrix_verdict'],'FAIL')
        self.assertEqual(result['completed_rounds'],1)
        self.assertEqual(result['planned_rounds'],2)

    def test_all_rounds_are_required_even_in_the_synthetic_small_matrix(self):
        code,calls,_,result=self.run_worker(self.synthetic_round)
        self.assertEqual((code,calls),(0,2))
        self.assertEqual(result['matrix_verdict'],'PASS')
        self.assertFalse(result['full_roadmap_acceptance'])
        self.assertFalse(result['restore_acceptance'])
        self.assertFalse(result['google_acceptance'])
        self.assertFalse(result['independent_qa'])

    def test_an_exception_keeps_active_attribution_and_invokes_reconciliation(self):
        def fail(*args):raise RuntimeError('synthetic_failure')
        code,calls,reconciles,result=self.run_worker(fail)
        self.assertEqual((code,calls,reconciles),(1,1,1))
        self.assertEqual(result['completed_rounds'],0)
        active=json.loads((self.directory/'active-round.public.json').read_bytes())
        self.assertTrue(active['absence_checked'])
        self.assertEqual(active['state'],'starting')

    def test_source_change_between_rounds_is_not_mixed_into_one_benchmark(self):
        code,calls,_,result=self.run_worker(self.synthetic_round,source_side_effect=[{},{},{'changed':'synthetic'},{'changed':'synthetic'}])
        self.assertEqual((code,calls),(1,1))
        self.assertFalse(result['sources_stable'])
        self.assertEqual(result['failure']['failure_code'],'source_changed_between_rounds')

    def test_batch_release_gate_precedes_uuid_and_supervision(self):
        with patch.object(batch.sys,'argv',['batch','--execute','--prerequisite',str(self.directory)]),\
             patch.object(batch.uuid,'uuid4',side_effect=AssertionError('Unexpected allocation.')):
            with self.assertRaises(ValueError):batch.main()

    def test_incomplete_matrix_cannot_pass_via_zero_process_exit(self):
        result={'matrix_verdict':'PASS','planned_rounds':52,'completed_rounds':51,'warmup_rounds':4,'measured_rounds':47,
                'sources_stable':True,'cleanup_verified':True,'full_roadmap_acceptance':False}
        self.assertFalse(batch.complete_matrix_result(result))

    def test_invalid_active_claim_never_constructs_a_cleanup_runtime(self):
        save(self.directory/'active-round.public.json',{'run_id':'foreign','absence_checked':True})
        with patch.object(batch,'Runtime',side_effect=AssertionError('Foreign cleanup attempted.')):
            with self.assertRaises(ValueError):batch.reconcile_active(self.directory)

    def test_supervisor_refuses_success_without_a_matrix_result(self):
        child=SimpleNamespace(pid=424240,stdin=BytesIO(),returncode=0,poll=lambda:0,wait=lambda **kwargs:0)
        job=SimpleNamespace(attach=lambda child:None,close=lambda:None)
        with patch.object(batch,'_WindowsJob',return_value=job),patch.object(batch.subprocess,'Popen',return_value=child),\
             redirect_stdout(StringIO()):
            code=batch.supervise(self.run,self.directory,self.directory)
        self.assertEqual(code,2)
        receipt=json.loads((self.directory/'supervision.public.json').read_bytes())
        self.assertEqual(receipt['terminal_exit'],0)
        self.assertFalse(receipt['matrix_result_verified'])


if __name__=='__main__':unittest.main()
