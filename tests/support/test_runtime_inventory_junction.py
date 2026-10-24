"""Actual Windows junction rejection for the accepted reporter bytes."""
import os
import subprocess
import unittest
import test_runtime_source_inventory as reporter_fixture


@unittest.skipUnless(os.name == 'nt', 'Windows junction boundary')
class JunctionBoundary(unittest.TestCase):
    setUp = reporter_fixture.ReporterTest.setUp
    remove_owned_fixture = reporter_fixture.ReporterTest.remove_owned_fixture
    run_reporter = reporter_fixture.ReporterTest.run_reporter
    assert_sanitized_failure = reporter_fixture.ReporterTest.assert_sanitized_failure

    def test_real_junction_is_rejected_without_disclosing_its_external_target(self):
        external = self.root.with_name(self.root.name + '-external')
        self.assertEqual(external.parent.resolve(), self.root.parent.resolve())
        external.mkdir()
        self.addCleanup(external.rmdir)
        link = self.root / 'resources/views/alias'
        quote = lambda value: "'" + str(value).replace("'", "''") + "'"
        command = 'New-Item -ItemType Junction -Path ' + quote(link) + ' -Target ' + quote(external) + ' -ErrorAction Stop | Out-Null'
        result = subprocess.run(['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', command], capture_output=True)
        self.assertEqual(result.returncode, 0)
        self.addCleanup(os.rmdir, link)
        self.assertEqual(link.resolve(), external.resolve())
        self.assert_sanitized_failure(self.run_reporter())
