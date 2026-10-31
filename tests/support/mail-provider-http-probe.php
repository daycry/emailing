<?php

use App\Enums\MailTransportMode;
use App\Services\MailProviders\BrevoApiTransport;
use App\Services\MailProviders\ProviderCredentials;
use App\Services\MailProviders\SendGridApiTransport;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\TransferStats;
use Illuminate\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\Email;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$script, $provider, $portValue, $ca, $output, $trust] = $argv;
if (! in_array($provider, ['brevo', 'sendgrid'], true) || ! ctype_digit($portValue) || (int) $portValue < 1 || (int) $portValue > 65535) {
    exit(2);
}
$facts = (object) ['profile_verified' => false, 'calls' => 0, 'observed_status' => null, 'error_type' => null, 'curl_errno' => null];
$curl = new CurlHandler;
$handler = function (RequestInterface $request, array $options) use ($provider, $portValue, $ca, $trust, $facts, $curl) {
    $facts->calls++;
    $expected = $provider === 'brevo' ? 'https://api.brevo.com/v3/smtp/email' : 'https://api.sendgrid.com/v3/mail/send';
    if ((string) $request->getUri() !== $expected || $options['verify'] !== true || $options['allow_redirects'] !== false) {
        throw new LogicException('Provider network policy differs from contract.');
    }
    $facts->profile_verified = true;
    $originalStats = $options['on_stats'] ?? null;
    $options['on_stats'] = function (TransferStats $stats) use ($originalStats, $facts): void {
        $error = $stats->getHandlerErrorData();
        $facts->curl_errno = is_int($error) ? $error : null;
        if ($originalStats !== null) {
            $originalStats($stats);
        }
    };
    $original = $options['on_headers'];
    $options['on_headers'] = function (ResponseInterface $response) use ($original, $facts): void {
        $facts->observed_status = $response->getStatusCode();
        $original($response);
    };
    // Only this test seam changes the peer. The provider policy still requires TLS.
    if ($trust === 'trusted') {
        $options['verify'] = $ca;
    }
    $options['proxy'] = '';
    $uri = new Uri('https://127.0.0.1:'.$portValue.$request->getUri()->getPath());

    return $curl($request->withUri($uri), $options)->then(null, function ($error) use ($facts) {
        $facts->error_type = is_object($error) ? get_class($error) : 'unknown';
        throw $error;
    });
};
$factory = new class($handler) extends Factory
{
    public function __construct(private Closure $wire)
    {
        parent::__construct();
    }

    protected function newPendingRequest(): PendingRequest
    {
        return parent::newPendingRequest()->setHandler($this->wire);
    }
};
$config = new Repository(['emailing' => ['provider_api_timeout' => 2]]);
$transport = $provider === 'brevo' ? new BrevoApiTransport($factory, $config) : new SendGridApiTransport($factory, $config);
$mode = $provider === 'brevo' ? MailTransportMode::BrevoApi : MailTransportMode::SendGridApi;
$credentials = new ProviderCredentials($mode, apiKey: 'synthetic-'.$provider.'-api-secret');
$email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('HTTPS fixture')->text('Synthetic content.');
$result = $transport->send($credentials, $email, '00000000-0000-4000-8000-000000000001');
file_put_contents($output, json_encode(['status' => $result->status, 'retryable' => $result->retryable,
    'retry_after' => $result->retryAfterSeconds, 'provider_message_id' => $result->providerMessageId,
    'profile_verified' => $facts->profile_verified, 'calls' => $facts->calls, 'observed_status' => $facts->observed_status,
    'error_type' => $facts->error_type, 'curl_errno' => $facts->curl_errno, 'tls_peer_verification_disabled' => false], JSON_THROW_ON_ERROR));
