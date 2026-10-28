"""Owned loopback tests for explicit SQL cut versus proxy budget exhaustion."""
import json
from pathlib import Path
import socket
import subprocess
import sys
import time
import unittest
from uuid import uuid4


class SqlProxyTest(unittest.TestCase):
    def setUp(self):
        self.root = Path(__file__).resolve().parents[2]
        self.directory = (self.root / 'storage' / ('sql-proxy-test-' + str(uuid4()))).resolve()
        assert self.directory.is_relative_to(self.root / 'storage')
        self.directory.mkdir()
        self.server = socket.socket()
        self.server.bind(('127.0.0.1', 0))
        self.server.listen(1)
        self.server.settimeout(3)
        self.process = None
        self.endpoints = []

    def tearDown(self):
        if self.process is not None:
            if self.process.poll() is None:
                self.process.kill()
            self.process.communicate(timeout=5)
        for endpoint in self.endpoints + [self.server]:
            endpoint.close()
        assert self.directory.is_relative_to(self.root / 'storage')
        for name in ['sql-ready.tmp', 'sql-ready', 'sql-cut.tmp', 'sql-cut', 'cut-sql']:
            (self.directory / name).unlink(missing_ok=True)
        self.directory.rmdir()

    def start(self, lifetime):
        self.process = subprocess.Popen([
            sys.executable, str(Path(__file__).with_name('delivery-sql-proxy.py')),
            str(self.directory), '127.0.0.1', str(self.server.getsockname()[1]), str(lifetime),
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        deadline = time.monotonic() + 5
        while not (self.directory / 'sql-ready').is_file():
            self.assertIsNone(self.process.poll())
            self.assertLess(time.monotonic(), deadline)
            time.sleep(.01)
        return json.loads((self.directory / 'sql-ready').read_text())['port']

    def connect(self, port):
        client = socket.create_connection(('127.0.0.1', port), timeout=3)
        upstream, _ = self.server.accept()
        upstream.settimeout(3)
        self.endpoints.extend([client, upstream])
        return client, upstream

    def terminal(self, exit_code, reason):
        stdout, stderr = self.process.communicate(timeout=5)
        self.assertEqual(exit_code, self.process.returncode)
        self.assertEqual(b'', stdout)
        self.assertEqual(b'', stderr)
        record = json.loads((self.directory / 'sql-cut').read_text())
        self.assertTrue(record['closed'])
        self.assertEqual(reason, record['reason'])

    def test_proxy_forwards_both_directions_until_explicit_cut(self):
        port = self.start(3)
        client, upstream = self.connect(port)
        client.sendall(b'owned request')
        self.assertEqual(b'owned request', upstream.recv(128))
        upstream.sendall(b'owned reply')
        self.assertEqual(b'owned reply', client.recv(128))
        (self.directory / 'cut-sql').write_text('cut')
        self.terminal(0, 'requested')

    def test_explicit_cut_also_stops_proxy_waiting_for_initial_connection(self):
        self.start(3)
        (self.directory / 'cut-sql').write_text('cut')
        self.terminal(0, 'requested')

    def test_budget_exhaustion_before_connection_is_a_failure(self):
        self.start(.2)
        self.terminal(1, 'budget_exhausted')

    def test_budget_exhaustion_after_connection_is_not_an_intentional_cut(self):
        port = self.start(.5)
        self.connect(port)
        self.terminal(1, 'budget_exhausted')


if __name__ == '__main__':
    unittest.main()
