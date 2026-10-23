<?php

namespace App\Services;

use App\Support\SmtpPreAcceptanceFailure;
use App\Support\SmtpRejected;
use App\Support\SmtpUnconfirmed;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\Email;
use Throwable;

class SmtpConnector
{
    public function __construct(private SmtpProtocolObserver $observer) {}

    // Internal adapter. Callers validate account and decide whether the endpoint is permitted.
    public function send(array $config, Email $email, ?\Closure $heartbeat = null): void
    {
        $transport = null;
        try {
            $security = $config['security'];
            $transport = new ObservedSmtpTransport($config['host'], $config['port'], $security === 'smtps', null, new NullLogger);
            $transport->heartbeat = $heartbeat;
            $transport->protocol = fn (string $stage, array $facts) => $this->observer->observe($stage, $facts);
            $transport->setAutoTls($security === 'starttls');
            $transport->setRequireTls($security !== 'plain');
            $stream = $transport->getStream();
            // A blocking SMTP operation is bounded below the minimum 15s lease.
            $stream->setTimeout(max(.1, min(5., (float) config('emailing.smtp_timeout', 5))));
            $this->observer->observe('configured', ['timeout_seconds' => $stream->getTimeout()]);
            $stream->setStreamOptions(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
            if (($config['username'] ?? '') !== '') {
                $transport->setUsername($config['username']);
                $transport->setPassword($config['password'] ?? '');
            }
            $transport->send($email);
        } catch (Throwable) {
            if ($transport?->accepted) {
                return;
            }
            $failure = $transport?->firstFailure;
            if ($failure && $failure['negative']) {
                throw new SmtpRejected($failure['stage'], $failure['code']);
            }
            if ($transport?->messageEntered && ! $transport->possibleAcceptance) {
                throw new SmtpPreAcceptanceFailure;
            }
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
