"""Synthetic daemon ownership checks; never calls Docker, SQL or the network."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
import json
import unittest
from types import SimpleNamespace
from unittest.mock import Mock

import test_guards as fixtures
from docker_scope import DockerScope,LABEL


class DockerScopeTests(unittest.TestCase):
    def setUp(self):
        fixtures.GuardTests.setUp(self)
        self.identifier='a'*64
        self.name='emailing-t13-'+self.run
        self.rows=[]
        self.inspection={'id':self.identifier,'name':'/'+self.name,'run':self.run}
        self.inspect_exit=0
        self.remove_on_inspect=False
        self.remove_on_stop=True
        self.remove_on_rm=True
        self.calls=[]
        self.runtime=Mock(out=self.directory)
        self.runtime.command.side_effect=self.command
        self.scope=DockerScope(self.runtime,self.run)

    def tearDown(self):fixtures.GuardTests.tearDown(self)

    def command(self,label,argv,**options):
        self.calls.append((label,argv))
        operation=argv[2]
        result=0
        if operation=='ls':
            self.assertIn('label='+LABEL+'='+self.run,argv)
            body=b'\n'.join(json.dumps(row).encode() for row in self.rows)
        elif operation=='inspect':
            self.assertEqual(argv[-1],self.identifier)
            self.assertNotIn('.Config.Env',argv[-2])
            if self.remove_on_inspect:self.rows=[]
            body=json.dumps(self.inspection).encode()
            result=self.inspect_exit
        elif operation in ('stop','rm'):
            self.assertEqual(argv[-1],self.identifier)
            if (operation=='rm' and self.remove_on_rm) or (operation=='stop' and self.remove_on_stop):self.rows=[]
            body=b''
        else:raise AssertionError('Unexpected daemon operation.')
        return SimpleNamespace(stdout=body,returncode=result)

    def owned_container(self):
        self.rows=[{'ID':self.identifier,'Names':self.name}]

    def mutations(self):
        return [argv[2] for _,argv in self.calls if argv[2] in ('stop','rm')]

    def test_absence_is_recorded_before_suite_ownership(self):
        self.scope.preflight()
        receipt=json.loads((self.directory/'daemon-attribution.public.json').read_bytes())
        self.assertTrue(receipt['absence_checked'])
        self.assertEqual(receipt['run_id'],self.run)
        self.assertEqual(self.mutations(),[])

    def test_existing_label_is_not_acquired_or_cleaned(self):
        self.owned_container()
        with self.assertRaises(ValueError):self.scope.preflight()
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])
        self.assertFalse((self.directory/'daemon-attribution.public.json').exists())

    def test_no_preflight_never_authorizes_cleanup(self):
        self.owned_container()
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.calls,[])

    def test_owned_survivor_is_attested_stopped_and_absence_verified(self):
        self.scope.preflight();self.owned_container()
        self.assertTrue(self.scope.cleanup())
        self.assertEqual(self.mutations(),['stop'])
        receipt=json.loads((self.directory/'daemon-cleanup.public.json').read_bytes())
        self.assertTrue(receipt['daemon_cleanup_verified'])
        self.assertEqual(receipt['owned_stopped_ids'],[self.identifier])

    def test_stopped_container_is_removed_only_by_its_verified_id(self):
        self.scope.preflight();self.owned_container();self.remove_on_stop=False
        self.assertTrue(self.scope.cleanup())
        self.assertEqual(self.mutations(),['stop','rm'])

    def test_label_mismatch_refuses_without_stopping(self):
        self.scope.preflight();self.owned_container();self.inspection['run']='foreign'
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])

    def test_surviving_container_cannot_be_reported_as_cleaned(self):
        self.scope.preflight();self.owned_container()
        self.remove_on_stop=self.remove_on_rm=False
        self.assertFalse(self.scope.cleanup())
        receipt=json.loads((self.directory/'daemon-cleanup.public.json').read_bytes())
        self.assertFalse(receipt['daemon_cleanup_verified'])

    def test_name_mismatch_refuses_without_stopping(self):
        self.scope.preflight();self.owned_container();self.inspection['name']='/another-container'
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])

    def test_unexpected_container_prefix_refuses_before_inspection(self):
        self.scope.preflight();self.rows=[{'ID':self.identifier,'Names':'another-application'}]
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])
        self.assertFalse(any(argv[2]=='inspect' for _,argv in self.calls))

    def test_duplicate_container_ids_cannot_authorize_cleanup(self):
        self.scope.preflight();self.owned_container();self.rows*=2
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])

    def test_removed_before_inspect_is_reconciled_by_scoped_absence(self):
        self.scope.preflight();self.owned_container();self.inspect_exit=1;self.remove_on_inspect=True
        self.assertTrue(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])

    def test_failed_inspect_of_a_survivor_is_not_successful_cleanup(self):
        self.scope.preflight();self.owned_container();self.inspect_exit=1
        self.assertFalse(self.scope.cleanup())
        self.assertEqual(self.mutations(),[])

    def test_daemon_unavailable_is_not_successful_cleanup(self):
        self.scope.preflight();self.runtime.command.side_effect=RuntimeError('Unavailable synthetic daemon.')
        self.assertFalse(self.scope.cleanup())
        receipt=json.loads((self.directory/'daemon-cleanup.public.json').read_bytes())
        self.assertFalse(receipt['daemon_cleanup_verified'])
        self.assertEqual(receipt['failure_class'],'RuntimeError')


if __name__=='__main__':unittest.main()
