"""Pure HTTP fakes: no socket, application, database, broker or real client."""
from dataclasses import asdict
import http.client
import importlib
import json
import ssl
import unittest
from unittest.mock import Mock, patch


class FakeResponse:
    def __init__(self, status=200, body=b'{"ok":true}', headers=None):
        self.status = status
        self.body = body
        self.headers = headers or {'Content-Type': 'application/json'}
        self.read_calls = []
        self.closed = False

    def getheaders(self):
        return list(self.headers.items())

    def read(self, count):
        self.read_calls.append(count)
        return self.body[:count]

    def close(self):
        self.closed = True


class BoundTransportTests(unittest.TestCase):
    def setUp(self):
        self.core = importlib.import_module('profile_transport')
        self.receipts = importlib.import_module('profile_receipts')
        self.response = FakeResponse()
        self.connection = Mock()
        self.connection.getresponse.return_value = self.response
        patcher = patch.object(self.core.http.client, 'HTTPSConnection', return_value=self.connection)
        self.factory = patcher.start()
        self.addCleanup(patcher.stop)
        self.origin = 'https://localhost:18443'

    def client(self, **options):
        return self.core.BoundHttpClient(self.origin, **options)

    def call(self, client=None, **options):
        values = {'operation': 'allowed_call', 'method': 'POST',
                  'url': self.origin + '/mcp', 'headers': None, 'body': b'{}'}
        values.update(options)
        return (client or self.client()).request(**values)

    def test_invalid_origins_fail_before_any_connection_without_echo(self):
        for origin in ['http://localhost:18443', 'https://user:fixture-secret@localhost:18443',
                       'https://localhost:18443/mcp', 'https://localhost:18443?',
                       'https://localhost:18443#fragment', '\nhttps://localhost:18443',
                       'https://localhost:0', 'https://localhost:99999']:
            with self.subTest(origin_kind=origin[:5]):
                with self.assertRaises(self.receipts.EvidenceError) as caught:
                    self.core.BoundHttpClient(origin)
                self.assertNotIn('fixture-secret', str(caught.exception))
        self.factory.assert_not_called()

    def test_authority_is_exact_including_port_and_host_spelling(self):
        client = self.client()
        for target in ['https://localhost:18444/mcp', 'https://127.0.0.1:18443/mcp',
                       'https://LOCALHOST:18443/mcp', 'https://elsewhere.example/mcp']:
            with self.subTest(target_kind='different authority'):
                with self.assertRaises(self.receipts.EvidenceError):
                    self.call(client, url=target)
        self.factory.assert_not_called()

    def test_operation_method_and_route_are_bound_to_the_accepted_contract(self):
        client = self.client()
        for values in [{'url': self.origin + '/oauth/token'},
                       {'url': self.origin + '/mcp?token=fixture-secret'},
                       {'url': self.origin + '/mcp/../oauth/token'}, {'method': 'GET'},
                       {'operation': 'readiness', 'method': 'GET', 'url': self.origin + '/login'}]:
            with self.subTest(fields=tuple(values)):
                with self.assertRaises(self.receipts.EvidenceError):
                    self.call(client, **values)
        self.factory.assert_not_called()

    def test_headers_cannot_override_authority_framing_or_inject_extra_credentials(self):
        client = self.client()
        for headers in [{'Host': 'elsewhere.example'}, {'Content-Length': '0'},
                        {'Transfer-Encoding': 'chunked'}, {'Proxy-Authorization': 'fixture-secret'},
                        {'Authorization': 'first', 'authorization': 'second'},
                        {'Authorization': 'Bearer fixture-secret\r\nX-Injected: 1'}]:
            with self.subTest(names=tuple(headers)):
                with self.assertRaises(self.receipts.EvidenceError) as caught:
                    self.call(client, headers=headers)
                self.assertNotIn('fixture-secret', str(caught.exception))
        self.factory.assert_not_called()

    def test_transport_uses_verified_tls_and_keeps_request_data_out_of_receipt(self):
        secret = 'fixture-private-credential'
        self.response = FakeResponse(body=b'{"value":"fixture-private-credential"}',
                                     headers={'Set-Cookie': secret})
        self.connection.getresponse.return_value = self.response
        result = self.call(headers={'Authorization': 'Bearer ' + secret}, body=secret.encode())
        context = self.factory.call_args.kwargs['context']
        self.assertEqual(context.verify_mode, ssl.CERT_REQUIRED)
        self.assertTrue(context.check_hostname)
        self.assertGreaterEqual(context.minimum_version, ssl.TLSVersion.TLSv1_2)
        self.assertEqual(self.connection.request.call_args.args[:2], ('POST', '/mcp'))
        sent = self.connection.request.call_args.kwargs
        self.assertEqual(sent['headers']['Host'], 'localhost:18443')
        self.assertEqual(sent['headers']['Authorization'], 'Bearer ' + secret)
        self.assertEqual(sent['body'], secret.encode())
        self.assertNotIn(secret, repr(result))
        self.assertNotIn(secret, json.dumps(asdict(result.receipt)))
        self.assertEqual(result.json_body()['value'], secret)
        self.assertTrue(self.response.closed)
        self.connection.close.assert_called_once()

    def test_redirect_is_observed_but_never_followed_or_retried(self):
        self.response = FakeResponse(302, b'fixture-secret', {'Location': 'https://elsewhere.example/mcp'})
        self.connection.getresponse.return_value = self.response
        result = self.call(headers={'Authorization': 'Bearer fixture-secret'})
        self.assertEqual(result.receipt.error, 'redirect_rejected')
        self.assertEqual(result.receipt.status, 302)
        self.assertEqual(result.body, b'')
        self.assertEqual(self.factory.call_count, 1)
        self.connection.request.assert_called_once()

    def test_response_reads_are_bounded_and_oversize_content_is_not_returned(self):
        self.response = FakeResponse(body=b'abcdef')
        self.connection.getresponse.return_value = self.response
        result = self.call(self.client(max_response_bytes=4))
        self.assertEqual(self.response.read_calls, [5])
        self.assertEqual(result.receipt.error, 'body_limit')
        self.assertEqual(result.receipt.response_bytes, 5)
        self.assertEqual(result.body, b'')
        self.assertTrue(self.response.closed)

    def test_tls_error_has_no_raw_certificate_or_hostname_message(self):
        self.connection.request.side_effect = ssl.SSLError('fixture-private-certificate')
        result = self.call()
        self.assertIsNone(result.receipt.status)
        self.assertEqual(result.receipt.error, 'tls_error')
        self.assertNotIn('fixture-private-certificate', repr(result))
        self.connection.close.assert_called_once()

    def test_timeout_is_unknown_and_does_not_resend_the_request(self):
        self.connection.request.side_effect = TimeoutError('fixture-private-timeout')
        result = self.call()
        self.assertEqual(result.receipt.error, 'timeout')
        self.assertIsNone(result.receipt.status)
        self.assertEqual(self.factory.call_count, 1)
        self.connection.request.assert_called_once()
        self.connection.close.assert_called_once()

    def test_http_error_body_stays_private_and_is_not_a_transport_success(self):
        self.response = FakeResponse(401, b'{"error":"invalid_token","debug":"fixture-private"}')
        self.connection.getresponse.return_value = self.response
        result = self.call()
        self.assertEqual(result.receipt.status, 401)
        self.assertEqual(result.receipt.error, 'http_error')
        self.assertEqual(result.json_body()['error'], 'invalid_token')
        self.assertNotIn('fixture-private', repr(result))
        self.assertNotIn('fixture-private', json.dumps(asdict(result.receipt)))

    def test_duplicate_json_fields_and_nonfinite_numbers_fail_without_echo(self):
        for body in [b'{"token":"fixture-private","token":"other"}',
                     b'{"number":NaN}', b'{"number":Infinity}', b'{"number":1e400}']:
            with self.subTest(body_kind='invalid JSON semantics'):
                self.connection.getresponse.return_value = FakeResponse(body=body)
                result = self.call()
                with self.assertRaises(self.receipts.EvidenceError) as caught:
                    result.json_body()
                self.assertNotIn('fixture-private', str(caught.exception))

    def test_invalid_utf8_or_json_fails_without_echoing_body(self):
        for body in [b'{"fixture-private":\xff}', b'fixture-private-not-json']:
            self.connection.getresponse.return_value = FakeResponse(body=body)
            result = self.call()
            with self.assertRaises(self.receipts.EvidenceError) as caught:
                result.json_body()
            self.assertNotIn('fixture-private', str(caught.exception))

    def test_insecure_tls_configuration_cannot_create_a_connection(self):
        context = Mock(verify_mode=ssl.CERT_NONE, check_hostname=False)
        with patch.object(self.core.ssl, 'create_default_context', return_value=context):
            with self.assertRaises(self.receipts.EvidenceError):
                self.client()
        self.factory.assert_not_called()

    def test_close_failure_is_recorded_without_its_raw_os_message(self):
        self.connection.close.side_effect = OSError('fixture-private-close')
        result = self.call()
        self.assertEqual(result.receipt.status, 200)
        self.assertEqual(result.receipt.error, 'network_error')
        self.assertNotIn('fixture-private-close', repr(result))

    def test_invalid_request_body_or_method_never_reaches_connection(self):
        client = self.client()
        for values in [{'body': 'fixture-private'}, {'method': 'CONNECT'},
                       {'method': 'POST\r\nfixture-private'}]:
            with self.assertRaises(self.receipts.EvidenceError) as caught:
                self.call(client, **values)
            self.assertNotIn('fixture-private', str(caught.exception))
        self.factory.assert_not_called()

    def test_limits_are_finite_positive_and_cannot_be_boolean(self):
        for values in [{'timeout': 0}, {'timeout': -1}, {'timeout': True},
                       {'timeout': float('nan')}, {'timeout': 301},
                       {'max_response_bytes': 0}, {'max_response_bytes': True},
                       {'max_response_bytes': 129 * 1024 * 1024}]:
            with self.assertRaises(self.receipts.EvidenceError):
                self.client(**values)
        self.factory.assert_not_called()

    def test_oauth_requests_use_only_their_fixed_local_routes(self):
        for operation, route in [('token_exchange', '/oauth/token'), ('token_revocation', '/oauth/revoke')]:
            result = self.call(operation=operation, url=self.origin + route,
                               headers={'Content-Type': 'application/x-www-form-urlencoded'},
                               body=b'token=fixture-private')
            self.assertEqual(self.connection.request.call_args.args[:2], ('POST', route))
            self.assertEqual(result.receipt.operation, operation)
            self.assertNotIn('fixture-private', repr(result))

    def test_incomplete_response_does_not_retry_or_expose_partial_private_data(self):
        self.response.read = Mock(side_effect=http.client.IncompleteRead(b'fixture-private', 10))
        result = self.call()
        self.assertEqual(result.receipt.status, 200)
        self.assertEqual(result.receipt.error, 'protocol_error')
        self.assertEqual(result.body, b'')
        self.assertNotIn('fixture-private', repr(result))
        self.connection.request.assert_called_once()
        self.connection.close.assert_called_once()


if __name__ == '__main__':
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(BoundTransportTests)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    print(json.dumps({'classification': 'unit fakes, no network or profile qualification',
                      'tests': result.testsRun, 'failures': len(result.failures),
                      'errors': len(result.errors), 'skipped': len(result.skipped),
                      'profile_qualified': False}), flush=True)
    raise SystemExit(0 if result.wasSuccessful() else 1)
