<?php

// Isolated local SMTP/TLS probe. Certificate and key are generated under ignored runtime storage.
[$script, $certificate, $key, $ready, $result, $mode] = $argv;
$context = stream_context_create(['ssl' => ['local_cert' => $certificate, 'local_pk' => $key]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $error, $description, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (! $server) {
    exit(2);
}
file_put_contents($ready, json_encode(['address' => stream_socket_get_name($server, false)], JSON_THROW_ON_ERROR));
$client = @stream_socket_accept($server, 8);
$probe = ['connected' => is_resource($client), 'ehlo_seen' => false, 'starttls_seen' => false, 'tls_accepted' => false];
if ($client) {
    stream_set_timeout($client, 5);
    if ($mode === 'starttls') {
        fwrite($client, "220 tls-fixture ESMTP\r\n");
        $probe['ehlo_seen'] = str_starts_with((string) fgets($client), 'EHLO ');
        fwrite($client, "250-tls-fixture\r\n250 STARTTLS\r\n");
        $probe['starttls_seen'] = trim((string) fgets($client)) === 'STARTTLS';
        fwrite($client, "220 Ready for TLS\r\n");
    }
    $probe['tls_accepted'] = @stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true;
    fclose($client);
}
fclose($server);
file_put_contents($result, json_encode($probe, JSON_THROW_ON_ERROR));
