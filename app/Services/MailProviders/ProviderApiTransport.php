<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;
use App\Support\TransportOutcome;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Shared network policy; concrete adapters only map their published contract. */
abstract class ProviderApiTransport
{
    public const MAX_RESPONSE_BYTES = 65536;

    private const MAX_REQUEST_BYTES = 4194304;

    private const HTTP_DATE_FORMAT = 'D, d M Y H:i:s \G\M\T';

    public function __construct(private Factory $http, private Repository $config) {}

    abstract protected function mode(): MailTransportMode;

    abstract protected function acceptedStatus(): int;

    abstract protected function headers(ProviderCredentials $credentials): array;

    abstract protected function payload(Email $email, string $reference): array;

    abstract protected function receipt(Response $response, string $body): ?string;

    final public function send(#[\SensitiveParameter] ProviderCredentials $credentials, Email $email, string $reference, ?\Closure $heartbeat = null): TransportOutcome
    {
        try {
            if ($credentials->mode !== $this->mode() || ! Str::isUuid($reference)) {
                throw new \InvalidArgumentException('Invalid provider API dispatch.');
            }
            $email->ensureValidity();
            if (count($email->getFrom()) !== 1 || count($email->getTo()) !== 1 || count($email->getReplyTo()) > 1
                || $email->getCc() !== [] || $email->getBcc() !== [] || $email->getAttachments() !== []
                || (($email->getTextBody() ?? '') === '' && ($email->getHtmlBody() ?? '') === '')) {
                throw new \InvalidArgumentException('Unsupported provider envelope.');
            }
            $body = json_encode($this->payload($email, $reference), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($body) > self::MAX_REQUEST_BYTES) {
                throw new \LengthException('Provider request exceeds its limit.');
            }
        } catch (\Throwable) {
            return new TransportOutcome('api_rejected', 'api_payload_rejected');
        }

        $buffer = Utils::streamFor('');
        $written = 0;
        $sink = FnStream::decorate($buffer, ['write' => function (string $chunk) use ($buffer, &$written): int {
            $written += strlen($chunk);
            if ($written > self::MAX_RESPONSE_BYTES) {
                throw new \LengthException('Provider response exceeds its limit.');
            }

            return $buffer->write($chunk);
        }]);
        $observed = null;
        try {
            if ($heartbeat !== null) {
                $heartbeat();
            }
            $timeout = max(.1, min(5., (float) $this->config->get('emailing.provider_api_timeout', 5)));
            $response = $this->http->acceptJson()->withHeaders($this->headers($credentials))
                ->withBody($body, 'application/json')->timeout($timeout)->connectTimeout(min(2., $timeout))
                ->withoutRedirecting()->withOptions([
                    'verify' => true, 'http_errors' => false, 'cookies' => false, 'read_timeout' => $timeout, 'sink' => $sink,
                    'on_headers' => function (ResponseInterface $headers) use (&$observed): void {
                        $observed = $headers;
                        $length = $headers->getHeaderLine('Content-Length');
                        if (ctype_digit($length) && (float) $length > self::MAX_RESPONSE_BYTES) {
                            throw new \LengthException('Provider response exceeds its limit.');
                        }
                    },
                ])->send('POST', $credentials->apiEndpoint());
            $observed = $response->toPsrResponse();
            if (($rejected = $this->rejection($observed)) !== null) {
                return $rejected;
            }
            if ($response->status() !== $this->acceptedStatus()) {
                return new TransportOutcome('api_unconfirmed', 'api_unconfirmed');
            }
            $stream = $observed->getBody();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $bytes = '';
            while (! $stream->eof() && strlen($bytes) <= self::MAX_RESPONSE_BYTES) {
                $chunk = $stream->read(min(8192, self::MAX_RESPONSE_BYTES + 1 - strlen($bytes)));
                if ($chunk === '' && ! $stream->eof()) {
                    throw new \RuntimeException('Provider body incomplete.');
                }
                $bytes .= $chunk;
            }
            if (strlen($bytes) > self::MAX_RESPONSE_BYTES) {
                throw new \LengthException('Provider response exceeds its limit.');
            }
            $id = $this->receipt($response, $bytes);
            if ($id === null || preg_match('/\A[\x21-\x7e]{1,255}\z/D', $id) !== 1) {
                return new TransportOutcome('api_unconfirmed', 'api_unconfirmed');
            }

            return new TransportOutcome('api_accepted', providerMessageId: $id);
        } catch (\Throwable) {
            // A failure after response headers can still prove a definite rejection.
            return ($observed === null ? null : $this->rejection($observed))
                ?? new TransportOutcome('api_unconfirmed', 'api_unconfirmed');
        } finally {
            $sink->close();
        }
    }

    protected function mailbox(Address $address): array
    {
        return ['email' => $address->getAddress()] + ($address->getName() === '' ? [] : ['name' => $address->getName()]);
    }

    private function rejection(ResponseInterface $response): ?TransportOutcome
    {
        $status = $response->getStatusCode();
        if ($status === 429) {
            return new TransportOutcome('api_rejected', 'api_rate_limited', retryable: true,
                retryAfterSeconds: $this->retryAfter($response->getHeaderLine('Retry-After')));
        }
        if ($status >= 400 && $status < 500 && $status !== 408) {
            return new TransportOutcome('api_rejected', in_array($status, [401, 403], true) ? 'api_authentication_rejected' : 'api_payload_rejected');
        }

        return null;
    }

    private function retryAfter(string $header): int
    {
        $value = trim($header);
        if (strlen($value) > 128 || $value === '') {
            return 60;
        }
        if (ctype_digit($value)) {
            return strlen($value) > 9 ? 86400 : max(1, min(86400, (int) $value));
        }
        $date = \DateTimeImmutable::createFromFormat(self::HTTP_DATE_FORMAT, $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format(self::HTTP_DATE_FORMAT) !== $value) {
            return 60;
        }
        $seconds = $date->getTimestamp() - time();

        return max(1, min(86400, $seconds));
    }
}
