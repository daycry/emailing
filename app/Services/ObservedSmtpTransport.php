<?php

namespace App\Services;

use App\Support\SmtpPolicyViolation;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class ObservedSmtpTransport extends EsmtpTransport
{
    public bool $accepted = false;

    public bool $possibleAcceptance = false;

    public bool $messageEntered = false;

    public ?array $firstFailure = null;

    public ?array $negotiationFailure = null;

    public ?\Closure $heartbeat = null;

    public ?\Closure $protocol = null;

    protected function doSend(SentMessage $message): void
    {
        $this->messageEntered = true;
        parent::doSend($message);
    }

    public function executeCommand(string $command, array $codes): string
    {
        $stage = match (true) {
            $command === "\r\n.\r\n" => 'data_final',
            $command === "DATA\r\n" => 'data',
            str_starts_with($command, 'MAIL FROM:') => 'mail',
            str_starts_with($command, 'RCPT TO:') => 'recipient',
            str_starts_with($command, 'EHLO ') || str_starts_with($command, 'HELO ') => 'helo',
            str_starts_with($command, 'RSET') || str_starts_with($command, 'QUIT') => 'cleanup',
            default => 'session',
        };
        if ($stage === 'mail') {
            // Reaching MAIL proves negotiation has completed, including a recovered HELO/AUTH.
            $this->negotiationFailure = null;
        }
        if ($stage !== 'cleanup' && $this->heartbeat) {
            ($this->heartbeat)();
        }
        if ($stage === 'data_final') {
            // Set BEFORE the write, including failure while writing the terminator.
            $this->possibleAcceptance = true;
        }
        $started = hrtime(true);
        $failed = false;
        $code = null;
        try {
            $reply = parent::executeCommand($command, $codes);
            if ($stage !== 'cleanup') {
                $this->negotiationFailure = null;
            }
            if ($stage === 'data_final') {
                $this->accepted = true;
            }

            return $reply;
        } catch (\Throwable $error) {
            $failed = true;
            $code = $error instanceof UnexpectedResponseException ? $error->getCode() : 0;
            if ($this->isTlsRequired() && str_starts_with($command, 'EHLO ')) {
                // ESMTP is mandatory for these TLS profiles. Symfony catches only its
                // TransportExceptionInterface here, so our policy exception prevents
                // its early HELO return from bypassing the real STARTTLS check.
                // SocketStream::isTLS() describes implicit TLS, not STARTTLS state.
                throw new SmtpPolicyViolation;
            }
            // EHLO/AUTH negotiation may recover internally (e.g. HELO fallback).
            // Only a failed message command can establish a latched negative outcome.
            if (in_array($stage, ['mail', 'recipient', 'data', 'data_final'], true) && $this->firstFailure === null) {
                $code = $error instanceof UnexpectedResponseException ? $error->getCode() : 0;
                $this->firstFailure = ['stage' => $stage, 'code' => $code, 'negative' => $code >= 400 && $code <= 599];
            } elseif ($stage !== 'cleanup' && (! str_starts_with($command, 'HELO ') || $this->negotiationFailure === null)) {
                // A later successful mechanism clears this. RSET/QUIT must not erase
                // the terminal rejection after all authenticators have been exhausted.
                $this->negotiationFailure = ['stage' => $stage, 'code' => $code, 'negative' => $code >= 400 && $code <= 599];
            }
            throw $error;
        } finally {
            if ($this->protocol) {
                ($this->protocol)('command', ['phase' => $stage, 'elapsed_seconds' => (hrtime(true) - $started) / 1e9, 'failed' => $failed, 'reply_code' => $code]);
            }
        }
    }
}
