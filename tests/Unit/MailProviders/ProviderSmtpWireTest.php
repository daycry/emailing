<?php

namespace Tests\Unit\MailProviders;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailProviderWireFixtures;

require_once dirname(__DIR__, 2).'/support/mail-provider-wire-fixtures.php';

class ProviderSmtpWireTest extends TestCase
{
    use MailProviderWireFixtures;

    #[DataProvider('sessions')]
    public function test_verified_tls_authentication_and_observed_smtp_outcomes(string $provider, string $security, string $outcome, string $status, bool $retryable): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $this->wireDirectory();
        $server = $client = null;
        $successful = false;
        try {
            $this->certificates($directory);
            $server = $this->launch([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', $root.'/tests/Fixtures/MailProviderSmtpServer.php',
                $directory.'/certificate.pem', $directory.'/key.pem', $directory.'/ready.json', $directory.'/smtp-facts.json',
                $security, $provider, $outcome], $directory, 'server');
            $ready = $this->waitJson($directory.'/ready.json', $server);
            $this->assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/D', $ready['address']);
            $port = (string) substr(strrchr($ready['address'], ':'), 1);
            $client = $this->launch([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', '-d', 'opcache.enable_cli=0',
                '-d', 'openssl.cafile='.$directory.'/ca.pem', $root.'/tests/support/mail-provider-smtp-probe.php',
                $provider.'_smtp', $security, $port, $directory.'/probe-facts.json'], $directory, 'client');
            $this->assertSame(0, $this->waitProcess($client));
            $this->assertSame(0, $this->waitProcess($server));
            $facts = json_decode(file_get_contents($directory.'/smtp-facts.json'), true, 512, JSON_THROW_ON_ERROR);
            $probe = json_decode(file_get_contents($directory.'/probe-facts.json'), true, 512, JSON_THROW_ON_ERROR);
            $data = ! in_array($outcome, ['auth_permanent', 'auth_temporary', 'downgrade', 'helo_permanent'], true);
            $tls = ! in_array($outcome, ['downgrade', 'helo_permanent'], true);
            $this->assertSame(['tls' => $tls, 'auth' => $tls, 'auth_attempted' => $tls, 'envelope' => $data,
                'data' => $data, 'correlation' => $data, 'reference' => $data], $facts);
            $this->assertTrue($probe['profile_verified']);
            $this->assertFalse($probe['tls_peer_verification_disabled']);
            $this->assertSame($status, $probe['status']);
            $this->assertSame($retryable, $probe['retryable']);
            if ($outcome === 'downgrade') {
                $this->assertSame('smtp_tls_required', $probe['diagnostic']);
            } elseif (in_array($outcome, ['auth_permanent', 'auth_temporary'], true)) {
                $this->assertSame('smtp_negative_session', $probe['diagnostic']);
            }
            $successful = true;
        } finally {
            $this->cleanupWireFixture($directory, [$server, $client], $successful);
        }
    }

    public static function sessions(): array
    {
        return [
            ['brevo', 'starttls', 'accepted', 'smtp_accepted', false],
            ['brevo', 'smtps', 'accepted', 'smtp_accepted', false],
            ['brevo', 'starttls', 'temporary', 'smtp_rejected', true],
            ['brevo', 'starttls', 'uncertain', 'smtp_unconfirmed', false],
            ['sendgrid', 'smtps', 'accepted', 'smtp_accepted', false],
            ['sendgrid', 'starttls', 'accepted', 'smtp_accepted', false],
            ['sendgrid', 'smtps', 'permanent', 'smtp_rejected', false],
            ['sendgrid', 'smtps', 'uncertain', 'smtp_unconfirmed', false],
            'auth-535' => ['brevo', 'starttls', 'auth_permanent', 'smtp_rejected', false],
            'downgrade' => ['sendgrid', 'starttls', 'downgrade', 'smtp_rejected', false],
            'auth-454' => ['sendgrid', 'starttls', 'auth_temporary', 'smtp_rejected', true],
            'auth-fallback' => ['brevo', 'starttls', 'auth_fallback', 'smtp_accepted', false],
            'helo-550' => ['brevo', 'starttls', 'helo_permanent', 'smtp_rejected', false],
        ];
    }
}
