"""Real owned subprocesses only; no Laravel, application DB, broker or HTTP."""
import importlib
from pathlib import Path
import socket
import sys
import tempfile
import time
import unittest
from unittest.mock import patch

from profile_receipts import EvidenceError

UNIT_ROOT = Path(__file__).resolve().parents[3] / 'storage/app/private/mcp-profile-unit-runs'
PREFIX = "import sys; assert sys.stdin.buffer.readline()==b'profile-execute-v1\\n'; "


class ProfileProcessTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_process')
        UNIT_ROOT.mkdir(exist_ok=True)
        self.temp = tempfile.TemporaryDirectory(prefix='process-', dir=UNIT_ROOT)
        self.root = Path(self.temp.name)

    def tearDown(self):
        self.release_owned_fixture(self.temp.cleanup)

    def release_owned_fixture(self, action):
        deadline = time.monotonic() + 2
        while True:
            assert self.root.resolve().is_relative_to(UNIT_ROOT.resolve())
            try:
                return action()
            except PermissionError as error:
                if sys.platform != 'win32' or getattr(error, 'winerror', None) != 32 or time.monotonic() >= deadline:
                    raise
                time.sleep(.01)

    def test_owned_fixture_retries_only_the_transient_windows_sharing_error(self):
        sharing = PermissionError('synthetic-sharing')
        sharing.winerror = 32
        action = unittest.mock.Mock(side_effect=[sharing, 'released'])
        with patch.object(sys, 'platform', 'win32'), patch('time.sleep') as sleep:
            self.assertEqual('released', self.release_owned_fixture(action))
        self.assertEqual(2, action.call_count)
        sleep.assert_called_once_with(.01)

    def test_owned_fixture_does_not_hide_a_nontransient_permission_error(self):
        denied = PermissionError('synthetic-permission')
        denied.winerror = 5
        action = unittest.mock.Mock(side_effect=denied)
        with patch('time.sleep') as sleep:
            with self.assertRaises(PermissionError):
                self.release_owned_fixture(action)
        self.assertEqual(1, action.call_count)
        sleep.assert_not_called()

    def command(self, code):
        return [sys.executable, '-E', '-s', '-B', '-c', PREFIX + code]

    def run_child(self, code, **kwargs):
        return self.core.run_bounded(self.command(code), cwd=self.root, **kwargs)

    def test_real_child_terminal_and_bounded_stdout_without_stderr_capture(self):
        result = self.run_child("print('ok'); sys.stderr.write('fixture-private')")
        self.assertEqual((result.exit_code, result.stdout), (0, b'ok\r\n' if sys.platform == 'win32' else b'ok\n'))
        self.assertNotIn('fixture-private', repr(result))
        self.assertGreater(result.elapsed_ns, 0)

    def test_nonzero_child_exit_is_preserved_and_not_reported_as_a_green(self):
        result = self.run_child('raise SystemExit(7)')
        self.assertEqual(result.exit_code, 7)

    def test_absolute_deadline_stops_a_real_blocked_child(self):
        before = time.monotonic()
        with self.assertRaisesRegex(EvidenceError, '^process_deadline_exceeded$'):
            self.run_child('import time; time.sleep(30)', timeout=0.4)
        self.assertLess(time.monotonic() - before, 8)

    def test_streaming_output_limit_stops_a_writer_and_never_echoes_payload(self):
        with self.assertRaisesRegex(EvidenceError, '^process_output_limit$'):
            self.run_child("sys.stdout.buffer.write(b'fixture-private'*100000); sys.stdout.flush()",
                           max_output_bytes=100, timeout=5)

    def test_retained_error_allows_owned_directory_cleanup_within_bounded_grace(self):
        retained = None
        try:
            self.run_child("sys.stdout.buffer.write(b'fixture-private'*100000); sys.stdout.flush()",
                           max_output_bytes=100, timeout=5)
        except EvidenceError as error:
            retained = error
        self.assertIsNotNone(retained)
        self.assertEqual('process_output_limit', str(retained))
        try:
            # A retained diagnostic must still allow bounded cleanup; Windows
            # may briefly report a sharing violation after process termination.
            assert self.root.resolve().is_relative_to(UNIT_ROOT.resolve())
            self.release_owned_fixture(self.root.rmdir)
            self.root.mkdir()
        finally:
            retained.with_traceback(None)

    def test_invalid_config_never_starts_a_process(self):
        with patch.object(self.core.subprocess, 'Popen', side_effect=AssertionError('must not start')):
            for changes in [{'timeout': 0}, {'timeout': float('nan')}, {'timeout': True},
                            {'max_output_bytes': 0}, {'max_output_bytes': True}]:
                with self.assertRaisesRegex(EvidenceError, '^invalid_process_config$'):
                    self.run_child("print('ok')", **changes)

    def test_os_error_is_fixed_and_does_not_echo_command_or_private_path(self):
        with patch.object(self.core.subprocess, 'Popen', side_effect=OSError('fixture-private')):
            with self.assertRaisesRegex(EvidenceError, '^process_execution_failed$'):
                self.run_child("print('ok')")

    def test_timeout_closes_an_owned_descendant_socket_not_only_the_parent(self):
        marker = self.root / 'child-ready.txt'
        listener = socket.socket()
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
        listener.close()
        descendant = ("import socket,time; from pathlib import Path; s=socket.socket(); "
                      f"s.bind(('127.0.0.1',{port})); s.listen(); "
                      f"Path({str(marker)!r}).write_text('ready'); time.sleep(30)")
        code = ("import subprocess,time; "
                f"subprocess.Popen([sys.executable,'-E','-s','-B','-c',{descendant!r}]); "
                "time.sleep(30)")
        with self.assertRaisesRegex(EvidenceError, '^process_deadline_exceeded$'):
            self.run_child(code, timeout=2)
        self.assertEqual(marker.read_text(), 'ready')
        rebound = socket.socket()
        try:
            if hasattr(socket, 'SO_EXCLUSIVEADDRUSE'):
                rebound.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)
            rebound.bind(('127.0.0.1', port))
        finally:
            rebound.close()

    def test_windows_containment_failure_never_opens_the_action_gate(self):
        if sys.platform != 'win32':
            return  # Windows-specific setup guard; no cross-platform qualification claim.
        target = self.root / 'must-not-exist.txt'
        with patch.object(self.core._WindowsJob, 'attach', side_effect=EvidenceError('process_containment_unavailable')):
            with self.assertRaisesRegex(EvidenceError, '^process_containment_unavailable$'):
                self.run_child(f"from pathlib import Path; Path({str(target)!r}).write_text('unexpected')")
        self.assertFalse(target.exists())


if __name__ == '__main__':
    unittest.main()
