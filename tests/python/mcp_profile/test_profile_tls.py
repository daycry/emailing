"""Real local TLS transport against a synthetic HTTP server, not the application."""
import http.server
from pathlib import Path
import shutil
import ssl
import subprocess
import tempfile
import threading
import unittest

from profile_transport import BoundHttpClient

UNIT_ROOT = Path(__file__).resolve().parents[3] / 'storage/app/private/mcp-profile-unit-runs'


class TransportTlsTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        UNIT_ROOT.mkdir(exist_ok=True)
        cls.temp = tempfile.TemporaryDirectory(prefix='tls-', dir=UNIT_ROOT)
        cls.directory = Path(cls.temp.name).resolve()
        cls.openssl = shutil.which('openssl')
        if not cls.openssl:
            raise RuntimeError('Native OpenSSL required for real local TLS checks')
        ca_config = '[req]\nprompt=no\ndistinguished_name=dn\nx509_extensions=ca_ext\n' \
                    '[dn]\nCN=Correo synthetic test CA\n[ca_ext]\n' \
                    'basicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n' \
                    'subjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid:always\n'
        leaf_config = '[req]\nprompt=no\ndistinguished_name=dn\n[dn]\nCN=localhost\n' \
                      '[server_ext]\nbasicConstraints=critical,CA:FALSE\n' \
                      'keyUsage=critical,digitalSignature,keyEncipherment\n' \
                      'extendedKeyUsage=serverAuth\nsubjectAltName=DNS:localhost\n' \
                      'subjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid:always\n'
        (cls.directory / 'ca.cnf').write_text(ca_config, encoding='ascii')
        (cls.directory / 'server.cnf').write_text(leaf_config, encoding='ascii')
        commands = [
            ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-days', '1',
             '-config', 'ca.cnf', '-keyout', 'ca-key.pem', '-out', 'ca.pem'],
            ['req', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-config', 'server.cnf',
             '-keyout', 'server-key.pem', '-out', 'server.csr'],
            ['x509', '-req', '-in', 'server.csr', '-CA', 'ca.pem', '-CAkey', 'ca-key.pem',
             '-set_serial', '1', '-out', 'server.pem', '-days', '1', '-sha256',
             '-extfile', 'server.cnf', '-extensions', 'server_ext']]
        for command in commands:
            result = subprocess.run([cls.openssl, *command], cwd=cls.directory,
                                    capture_output=True, timeout=30)
            if result.returncode != 0:
                raise RuntimeError('Synthetic TLS certificate generation failed')
        cls.requests_seen = 0

        class Handler(http.server.BaseHTTPRequestHandler):
            protocol_version = 'HTTP/1.1'

            def do_GET(self):
                cls.requests_seen += 1
                body = b'{"status":"ok"}'
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Content-Length', str(len(body)))
                self.end_headers()
                self.wfile.write(body)

            def do_POST(self):
                cls.requests_seen += 1
                length = int(self.headers.get('Content-Length', '0'))
                self.rfile.read(length)
                self.send_response(302)
                self.send_header('Location', 'https://fixture-private.invalid/mcp')
                self.send_header('Content-Length', '0')
                self.end_headers()

            def log_message(self, *args):
                pass

        cls.server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
        cls.server.daemon_threads = True
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.minimum_version = ssl.TLSVersion.TLSv1_2
        context.load_cert_chain(cls.directory / 'server.pem', cls.directory / 'server-key.pem')
        cls.server.socket = context.wrap_socket(cls.server.socket, server_side=True)
        cls.port = cls.server.server_address[1]
        cls.origin = 'https://localhost:' + str(cls.port)
        cls.thread = threading.Thread(target=cls.server.serve_forever,
                                      kwargs={'poll_interval': 0.05}, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        if hasattr(cls, 'server'):
            cls.server.shutdown()
            cls.server.server_close()
            cls.thread.join(timeout=5)
            if cls.thread.is_alive():
                raise RuntimeError('Owned synthetic TLS server did not stop')
        if hasattr(cls, 'temp'):
            assert cls.directory.is_relative_to(UNIT_ROOT.resolve())
            cls.temp.cleanup()

    def client(self, origin=None, **kwargs):
        return BoundHttpClient(origin or self.origin, timeout=5,
                               ca_file=str(self.directory / 'ca.pem'), **kwargs)

    def test_real_verified_tls_accepts_only_the_configured_ca_and_hostname(self):
        exchange = self.client().request('readiness', 'GET', self.origin + '/up')
        self.assertEqual(exchange.receipt.status, 200)
        self.assertIsNone(exchange.receipt.error)
        self.assertEqual(exchange.json_body(), {'status': 'ok'})

    def test_unknown_ca_fails_before_any_http_request(self):
        before = self.requests_seen
        exchange = BoundHttpClient(self.origin, timeout=5).request('readiness', 'GET', self.origin + '/up')
        self.assertEqual(exchange.receipt.error, 'tls_error')
        self.assertIsNone(exchange.receipt.status)
        self.assertEqual(self.requests_seen, before)

    def test_wrong_hostname_fails_even_when_the_ca_is_trusted(self):
        before = self.requests_seen
        origin = 'https://127.0.0.1:' + str(self.port)
        exchange = self.client(origin).request('readiness', 'GET', origin + '/up')
        self.assertEqual(exchange.receipt.error, 'tls_error')
        self.assertEqual(self.requests_seen, before)

    def test_real_redirect_response_is_not_followed(self):
        before = self.requests_seen
        exchange = self.client().request('allowed_call', 'POST', self.origin + '/mcp', body=b'{}')
        self.assertEqual(exchange.receipt.status, 302)
        self.assertEqual(exchange.receipt.error, 'redirect_rejected')
        self.assertEqual(self.requests_seen, before + 1)
        self.assertEqual(exchange.body, b'')

    def test_real_body_limit_discards_the_oversized_response(self):
        exchange = self.client(max_response_bytes=3).request('readiness', 'GET', self.origin + '/up')
        self.assertEqual(exchange.receipt.error, 'body_limit')
        self.assertEqual(exchange.receipt.response_bytes, 4)
        self.assertEqual(exchange.body, b'')


if __name__ == '__main__':
    unittest.main()
