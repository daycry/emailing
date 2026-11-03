"""Regression launcher contracts, including actual gated PHP with synthetic code only."""
from pathlib import Path
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[2]/'scripts/broker_lab'))
from contextlib import redirect_stderr,redirect_stdout
from io import StringIO,BytesIO
import json
import subprocess
import unittest
from unittest.mock import patch,Mock
import xml.etree.ElementTree as ET

import regression
import test_guards as fixtures
from runtime import ROOT,FLAGS,GATE,RESERVED


class RegressionTests(unittest.TestCase):
    def setUp(self):fixtures.GuardTests.setUp(self)
    def tearDown(self):fixtures.GuardTests.tearDown(self)

    def document(self,body):
        path=self.directory/'synthetic-junit.xml'
        path.write_text(body,encoding='utf-8')
        return path

    def test_complete_selection_uses_unit_and_feature_files(self):
        selection=regression.selection()
        self.assertTrue(selection)
        self.assertEqual(selection,sorted(set(selection)))
        self.assertTrue(all(name.startswith(('tests/Unit/','tests/Feature/')) and name.endswith('Test.php') for name in selection))

    def test_filter_is_rejected_instead_of_producing_a_partial_baseline(self):
        with patch.object(sys,'argv',['regression','--filter','one-test']),redirect_stderr(StringIO()):
            with self.assertRaises(SystemExit) as raised:regression.main()
        self.assertEqual(raised.exception.code,2)

    def test_whole_suite_deadline_does_not_modify_phpunit_timeouts(self):
        with patch.object(sys,'argv',['regression','--deadline','1']):
            with self.assertRaises(ValueError):regression.main()

    def test_empty_junit_never_means_a_passing_suite(self):
        path=self.document('<testsuites><testsuite/></testsuites>')
        with self.assertRaises(ValueError):regression.junit_summary(path,0)

    def test_truncated_junit_is_not_accepted(self):
        path=self.document('<testsuites><testsuite><testcase name="incomplete">')
        with self.assertRaises(ET.ParseError):regression.junit_summary(path,0)

    def test_duplicate_case_identifiers_are_rejected(self):
        path=self.document('<testsuites><testcase class="Synthetic" name="same"/><testcase class="Synthetic" name="same"/></testsuites>')
        with self.assertRaises(ValueError):regression.junit_summary(path,0)

    def test_summary_keeps_counts_and_problem_ids_without_exception_messages(self):
        path=self.document('<testsuites><testsuite><testcase class="Synthetic" name="success" assertions="2"/>'
             '<testcase class="Synthetic" name="failure" assertions="1"><failure message="do-not-export-value"/></testcase>'
             '<testcase class="Synthetic" name="skipped"><skipped/></testcase></testsuite></testsuites>')
        summary=regression.junit_summary(path,1)
        self.assertEqual((summary['cases'],summary['assertions'],summary['failures'],summary['errors'],summary['skipped']),(3,3,1,0,1))
        self.assertNotIn('do-not-export-value',json.dumps(summary))

    def test_negative_assertion_counts_are_not_valid_evidence(self):
        path=self.document('<testsuites><testcase class="Synthetic" name="invalid" assertions="-1"/></testsuites>')
        with self.assertRaises(ValueError):regression.junit_summary(path,0)

    def test_interruption_does_not_claim_cleanup_when_daemon_reconciliation_fails(self):
        events=[]
        child=Mock(pid=101,stdin=BytesIO(),returncode=2)
        child.poll.return_value=None
        child.wait.return_value=2
        job=Mock()
        job.close.side_effect=lambda:events.append('native_closed')
        scope=Mock()
        scope.cleanup.side_effect=lambda:events.append('daemon_reconciled') or False
        with patch.object(regression,'sources',return_value={'app/Synthetic.php':'a'*64}),\
             patch.object(regression,'selection',return_value=['tests/Unit/SyntheticTest.php']),\
             patch.object(regression,'_WindowsJob',return_value=job),\
             patch.object(regression,'DockerScope',return_value=scope,create=True),\
             patch.object(regression.subprocess,'Popen',return_value=child),redirect_stdout(StringIO()):
            status=regression.supervise(self.run,self.directory,0)
        receipt=json.loads((self.directory/'command-receipt.public.json').read_bytes())
        self.assertEqual(status,2)
        self.assertEqual(receipt['termination_reason'],'supervision_deadline_exceeded')
        self.assertFalse(receipt['tree_cleanup_succeeded'])
        self.assertFalse(receipt['daemon_cleanup_verified'])
        self.assertEqual(events,['native_closed','daemon_reconciled'])

    def invoke_php(self,gate):
        entry=self.directory/'argument-fixture.php'
        entry.write_text("<?php file_put_contents(__DIR__.'/executed.marker','executed'); echo json_encode(array_values($argv),JSON_THROW_ON_ERROR);",encoding='utf-8')
        arguments=['--colors=never','a path with spaces','literal-$(not-a-shell)']
        command=regression.gated_php_command(entry,arguments)
        command.insert(1,'-n')  # This synthetic entrypoint needs no application or extensions.
        child=subprocess.Popen(command,cwd=ROOT,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=FLAGS)
        if child.pid in RESERVED:
            child.stdin.close()
            raise RuntimeError('protected_process_identity_refused')
        try:
            stdout,stderr=child.communicate(gate,timeout=5)
        finally:
            if child.poll() is None:child.kill();child.wait(timeout=5)
        return child.returncode,stdout,stderr,entry,arguments

    def test_php_does_not_load_the_entrypoint_before_the_gate(self):
        code,stdout,stderr,_,_=self.invoke_php(b'wrong-gate\n')
        self.assertEqual((code,stdout,stderr),(2,b'',b''))
        self.assertFalse((self.directory/'executed.marker').exists())

    def test_gated_php_preserves_argument_boundaries_without_a_shell(self):
        code,stdout,stderr,entry,arguments=self.invoke_php(GATE)
        self.assertEqual((code,stderr),(0,b''))
        self.assertEqual(json.loads(stdout),[str(entry),*arguments])
        self.assertTrue((self.directory/'executed.marker').is_file())


if __name__=='__main__':unittest.main()
