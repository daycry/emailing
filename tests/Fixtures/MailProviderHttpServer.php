<?php

// A single owned HTTPS peer. Its evidence contains booleans, not request data.
[$script, $certificate, $key, $ready, $result, $provider, $outcome] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'local_pk' => $key,
    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $error, $description, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (! $server) {
    exit(2);
}
file_put_contents($ready, json_encode(['address' => stream_socket_get_name($server, false)], JSON_THROW_ON_ERROR));
$client = @stream_socket_accept($server, 8);
$facts = ['tls' => false, 'request' => false, 'auth' => false, 'correlation' => false];
if ($client) {
    stream_set_timeout($client, 5);
    $facts['tls'] = @stream_socket_enable_crypto($client, true) === true;
    if ($facts['tls']) {
        $first = fgets($client);
        $headers = [];
        $headerBytes = 0;
        while (is_string($line = fgets($client)) && trim($line) !== '' && ($headerBytes += strlen($line)) <= 16384) {
            $pair = explode(':', $line, 2);
            if (count($pair) === 2) {
                $headers[strtolower(trim($pair[0]))] = trim($pair[1]);
            }
        }
        $path = $provider === 'brevo' ? '/v3/smtp/email' : '/v3/mail/send';
        $facts['request'] = $first === 'POST '.$path." HTTP/1.1\r\n";
        $facts['auth'] = $provider === 'brevo' ? ($headers['api-key'] ?? '') === 'synthetic-brevo-api-secret'
            : ($headers['authorization'] ?? '') === 'Bearer synthetic-sendgrid-api-secret';
        $length = ctype_digit($headers['content-length'] ?? '') ? (int) $headers['content-length'] : -1;
        $body = '';
        if ($length >= 0 && $length <= 4194304) {
            while (strlen($body) < $length && is_string($chunk = fread($client, min(8192, $length - strlen($body)))) && $chunk !== '') {
                $body .= $chunk;
            }
            $payload = json_decode($body, true);
            $reference = '00000000-0000-4000-8000-000000000001';
            $facts['correlation'] = $provider === 'brevo' ? ($payload['headers']['X-Mailin-custom'] ?? null) === 'emailing_reference:'.$reference
                : ($payload['custom_args']['emailing_reference'] ?? null) === $reference;
        }
        unset($headers, $payload, $body);
        if ($outcome !== 'uncertain') {
            $status = match ($outcome) {
                'rate' => 429, 'redirect' => 302, default => $provider === 'brevo' ? 201 : 202
            };
            $response = $provider === 'brevo' ? '{"messageId":"synthetic-brevo-message-id"}' : '';
            if ($outcome === 'rate') {
                $response = '<html>rate limited</html>';
            }
            $head = 'HTTP/1.1 '.$status." Fixture\r\nContent-Type: application/json\r\nConnection: close\r\n";
            $head .= $provider === 'sendgrid' ? "X-Message-Id: synthetic-sendgrid-message-id\r\n" : '';
            $head .= $outcome === 'rate' ? "Retry-After: 120\r\n" : '';
            $head .= $outcome === 'redirect' ? "Location: https://untrusted.example.test/\r\n" : '';
            if ($outcome === 'oversized') {
                $response .= str_repeat(' ', 65536);
                @fwrite($client, $head."Transfer-Encoding: chunked\r\n\r\n");
                foreach (str_split($response, 8192) as $chunk) {
                    if (@fwrite($client, dechex(strlen($chunk))."\r\n".$chunk."\r\n") === false) {
                        break;
                    }
                }
                @fwrite($client, "0\r\n\r\n");
            } else {
                @fwrite($client, $head.'Content-Length: '.strlen($response)."\r\n\r\n".$response);
            }
        }
    }
    fclose($client);
}
fclose($server);
file_put_contents($result, json_encode($facts, JSON_THROW_ON_ERROR));
