<?php

namespace App\Services;

use App\Support\SmtpUnconfirmed;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Throwable;

class SmtpConnector
{
    // Internal adapter. Callers validate account and decide whether the endpoint is permitted.
    public function send(array $config, Email $email): void
    {
        $transport = null;
        try {
            $security = $config['security'];
            $transport = new EsmtpTransport($config['host'], $config['port'], $security === 'smtps', null, new NullLogger);
            $transport->setAutoTls($security === 'starttls');
            $transport->setRequireTls($security !== 'plain');
            $stream = $transport->getStream();
            $stream->setTimeout((float) config('emailing.smtp_timeout', 5));
            $stream->setStreamOptions(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
            if (($config['username'] ?? '') !== '') {
                $transport->setUsername($config['username']);
                $transport->setPassword($config['password'] ?? '');
            }
            $transport->send($email);
        } catch (Throwable) {
            // Raw SMTP errors/debug may contain AUTH, DSNs, paths or message contents.
            throw new SmtpUnconfirmed;
        } finally {
            if ($transport !== null) {
                try {
                    $transport->stop();
                } catch (Throwable) {
                    // Closing a failed connection must not disclose debug or mask uncertainty.
                }
            }
        }
    }
}
