<?php

// One owned loopback SMTP peer; writes facts, never AUTH values or message bodies.
[$script, $certificate, $key, $ready, $result, $security, $provider, $outcome] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'local_pk' => $key,
    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER | STREAM_CRYPTO_METHOD_TLSv1_3_SERVER]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $error, $description, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (! $server) {
    exit(2);
}
file_put_contents($ready, json_encode(['address' => stream_socket_get_name($server, false)], JSON_THROW_ON_ERROR));
$client = @stream_socket_accept($server, 8);
$facts = ['tls' => false, 'auth' => false, 'auth_attempted' => false, 'envelope' => false, 'data' => false, 'correlation' => false, 'reference' => false];
if ($client) {
    stream_set_timeout($client, 5);
    if ($security === 'smtps') {
        $facts['tls'] = @stream_socket_enable_crypto($client, true) === true;
    }
    fwrite($client, "220 fixture ESMTP\r\n");
    while (is_string($line = fgets($client))) {
        if (str_starts_with($line, 'EHLO ')) {
            fwrite($client, in_array($outcome, ['downgrade', 'helo_permanent'], true) ? "500 EHLO refused\r\n"
                : "250-fixture\r\n".($facts['tls'] ? '' : "250-STARTTLS\r\n")."250 AUTH LOGIN PLAIN\r\n");
        } elseif (str_starts_with($line, 'HELO ')) {
            fwrite($client, $outcome === 'helo_permanent' ? "550 HELO refused\r\n" : "250 HELO accepted\r\n");
        } elseif (trim($line) === 'STARTTLS') {
            fwrite($client, "220 Ready for TLS\r\n");
            $facts['tls'] = @stream_socket_enable_crypto($client, true) === true;
            if (! $facts['tls']) {
                break;
            }
        } elseif (str_starts_with($line, 'AUTH ')) {
            $facts['auth_attempted'] = true;
            if (! $facts['tls']) {
                fwrite($client, "530 TLS required\r\n");
                break;
            }
            if (trim($line) === 'AUTH LOGIN') {
                fwrite($client, '334 '.base64_encode('Username:')."\r\n");
                $username = base64_decode(trim((string) fgets($client)), true);
                fwrite($client, '334 '.base64_encode('Password:')."\r\n");
                $password = base64_decode(trim((string) fgets($client)), true);
            } else {
                $parts = explode("\0", (string) base64_decode(trim(substr($line, strlen('AUTH PLAIN '))), true));
                $username = $parts[1] ?? null;
                $password = $parts[2] ?? null;
            }
            $facts['auth'] = $username === ($provider === 'sendgrid' ? 'apikey' : 'synthetic-smtp-user')
                && $password === 'synthetic-'.$provider.'-smtp-secret';
            unset($username, $password, $parts);
            fwrite($client, match (true) {
                $outcome === 'auth_permanent' => "535 Authentication failed\r\n",
                $outcome === 'auth_temporary' => "454 Authentication temporarily unavailable\r\n",
                $outcome === 'auth_fallback' && trim($line) === 'AUTH LOGIN' => "535 LOGIN refused\r\n",
                $facts['auth'] => "235 Authenticated\r\n",
                default => "535 Authentication failed\r\n",
            });
        } elseif (str_starts_with($line, 'MAIL FROM:') || str_starts_with($line, 'RCPT TO:')) {
            $facts['envelope'] = true;
            fwrite($client, $facts['auth'] || $outcome === 'downgrade' ? "250 OK\r\n" : "530 Authenticate\r\n");
        } elseif (trim($line) === 'DATA') {
            fwrite($client, "354 Send message\r\n");
            $body = '';
            while (is_string($part = fgets($client)) && $part !== ".\r\n") {
                $body .= $part;
                if (strlen($body) > 65536) {
                    break 2;
                }
            }
            $facts['data'] = $part === ".\r\n";
            $facts['correlation'] = str_contains($body, 'emailing_reference');
            $facts['reference'] = str_contains($body, '00000000-0000-4000-8000-000000000001');
            unset($body);
            if ($outcome === 'uncertain') {
                break;
            }
            fwrite($client, match ($outcome) {
                'temporary' => "450 Synthetic transient refusal\r\n", 'permanent' => "550 Synthetic permanent refusal\r\n",
                default => "250 Accepted by fixture\r\n",
            });
        } elseif (trim($line) === 'RSET') {
            fwrite($client, "250 Reset\r\n");
        } elseif (trim($line) === 'QUIT') {
            fwrite($client, "221 Bye\r\n");
            break;
        } else {
            fwrite($client, "500 Unsupported fixture command\r\n");
        }
    }
    fclose($client);
}
fclose($server);
file_put_contents($result, json_encode($facts, JSON_THROW_ON_ERROR));
