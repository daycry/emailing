<?php

namespace App\Services\MailProviders;

final class SendGridWebhookKey
{
    public static function parse(string $value): \OpenSSLAsymmetricKey|false
    {
        if (strlen($value) > 4096 || $value === '') {
            return false;
        }
        $pem = trim($value);
        if (! str_starts_with($pem, '-----BEGIN PUBLIC KEY-----')) {
            $der = base64_decode($pem, true);
            if ($der === false) {
                return false;
            }
            $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
        }
        $key = @openssl_pkey_get_public($pem);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        return $details && $details['type'] === OPENSSL_KEYTYPE_EC && ($details['ec']['curve_name'] ?? null) === 'prime256v1'
            ? $key : false;
    }
}
