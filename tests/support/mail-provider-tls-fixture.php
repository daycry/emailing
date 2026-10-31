<?php

namespace Tests\Support;

/** Test-only PKI, scoped to an owned runtime directory; never installs a CA. */
final class MailProviderTlsFixture
{
    public static function create(string $directory, callable $check): void
    {
        $config = $directory.'/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\nprompt=no\n[dn]\nCN=Emailing fixture\n"
            ."[ca_ext]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\nsubjectKeyIdentifier=hash\n"
            ."[server_ext]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\n"
            ."extendedKeyUsage=serverAuth\nsubjectAltName=IP:127.0.0.1,DNS:localhost\nsubjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid,issuer\n");
        $options = ['config' => $config, 'digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
        $caKey = openssl_pkey_new($options);
        $check($caKey !== false);
        $caCsr = openssl_csr_new(['commonName' => 'Emailing fixture CA'], $caKey, $options);
        $check($caCsr !== false);
        $ca = openssl_csr_sign($caCsr, null, $caKey, 1, $options + ['x509_extensions' => 'ca_ext']);
        $check($ca !== false);
        $key = openssl_pkey_new($options);
        $check($key !== false);
        $csr = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
        $check($csr !== false);
        $certificate = openssl_csr_sign($csr, $ca, $caKey, 1, $options + ['x509_extensions' => 'server_ext']);
        $check($certificate !== false);
        $check(openssl_x509_export_to_file($ca, $directory.'/ca.pem'));
        $check(openssl_x509_export_to_file($certificate, $directory.'/certificate.pem'));
        $check(openssl_pkey_export_to_file($key, $directory.'/key.pem', null, $options));
    }
}
