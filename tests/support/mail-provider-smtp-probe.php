<?php

use App\Enums\MailTransportMode;
use App\Services\MailProviders\ProviderCredentials;
use App\Services\MailProviders\ProviderSmtpTransport;
use App\Services\SmtpConnector;
use App\Services\SmtpProtocolObserver;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Symfony\Component\Mime\Email;

require dirname(__DIR__, 2).'/vendor/autoload.php';

[$script, $modeValue, $security, $portValue, $output] = $argv;
$mode = MailTransportMode::tryFrom($modeValue);
if (! $mode || ! $mode->isExternal() || $mode->isApi() || ! ctype_digit($portValue) || (int) $portValue < 1 || (int) $portValue > 65535) {
    exit(2);
}
$container = new Container;
$container->instance('config', new Repository(['emailing' => ['smtp_timeout' => 2]]));
Container::setInstance($container);
$facts = (object) ['profile_verified' => false];
$connector = new class(new SmtpProtocolObserver, (int) $portValue, $mode, $facts) extends SmtpConnector
{
    public function __construct(SmtpProtocolObserver $observer, private int $port, private MailTransportMode $mode, private object $facts)
    {
        parent::__construct($observer);
    }

    public function send(array $config, Email $email, ?Closure $heartbeat = null): void
    {
        $expected = $this->mode->provider() === 'brevo' ? 'smtp-relay.brevo.com' : 'smtp.sendgrid.net';
        if ($config['host'] !== $expected || $config['port'] !== ($config['security'] === 'smtps' ? 465 : 587)) {
            throw new LogicException('Provider profile differs from contract.');
        }
        $this->facts->profile_verified = true;
        // Test-only network seam: real TLS/SMTP is sent solely to the owned peer.
        parent::send(array_replace($config, ['host' => '127.0.0.1', 'port' => $this->port]), $email, $heartbeat);
    }
};
$credentials = new ProviderCredentials($mode, smtpUsername: 'synthetic-smtp-user',
    smtpPassword: 'synthetic-'.$mode->provider().'-smtp-secret', smtpSecurity: $security);
$email = (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('SMTP fixture')->text('Synthetic content.');
$result = (new ProviderSmtpTransport($connector))->send($credentials, $email, '00000000-0000-4000-8000-000000000001');
file_put_contents($output, json_encode(['status' => $result->status, 'retryable' => $result->retryable, 'diagnostic' => $result->diagnostic,
    'profile_verified' => $facts->profile_verified, 'tls_peer_verification_disabled' => false], JSON_THROW_ON_ERROR));
