<?php

namespace Tests\Unit\MailProviders;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\MailProviderWireFixtures;

require_once dirname(__DIR__, 2).'/support/mail-provider-wire-fixtures.php';

class ProviderApiWireTest extends TestCase
{
    use MailProviderWireFixtures;

    #[DataProvider('sessions')]
    public function test_actual_https_enforces_trust_bounds_and_single_attempt_policy(string $provider, string $outcome, string $trust, string $status, bool $retryable): void
    {
        $root = dirname(__DIR__, 3);
        $directory = $this->wireDirectory();
        $server = $client = null;
        $successful = false;
        try {
            $this->certificates($directory);
            $server = $this->launch([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', $root.'/tests/Fixtures/MailProviderHttpServer.php',
                $directory.'/certificate.pem', $directory.'/key.pem', $directory.'/ready.json', $directory.'/http-facts.json',
                $provider, $outcome], $directory, 'server');
            $ready = $this->waitJson($directory.'/ready.json', $server);
            $this->assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/D', $ready['address']);
            $port = (string) substr(strrchr($ready['address'], ':'), 1);
            $client = $this->launch([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', '-d', 'opcache.enable_cli=0',
                $root.'/tests/support/mail-provider-http-probe.php', $provider, $port, $directory.'/ca.pem', $directory.'/probe-facts.json', $trust], $directory, 'client');
            $this->assertSame(0, $this->waitProcess($client));
            $this->assertSame(0, $this->waitProcess($server));
            $facts = json_decode(file_get_contents($directory.'/http-facts.json'), true, 512, JSON_THROW_ON_ERROR);
            $probe = json_decode(file_get_contents($directory.'/probe-facts.json'), true, 512, JSON_THROW_ON_ERROR);
            $connected = $trust === 'trusted';
            // TLS 1.3 can complete the server's half before it receives the client's alert.
            // The client certificate verdict and zero HTTP/auth bytes are authoritative.
            $this->assertIsBool($facts['tls']);
            $this->assertSame(['request' => $connected, 'auth' => $connected, 'correlation' => $connected], array_diff_key($facts, ['tls' => true]));
            if ($connected) {
                $this->assertTrue($facts['tls']);
            } else {
                $this->assertSame(60, $probe['curl_errno']); // libcurl certificate verification; PHP aliases differ by build.
                $this->assertNull($probe['observed_status']);
            }
            $this->assertTrue($probe['profile_verified']);
            $this->assertSame(1, $probe['calls']);
            $this->assertFalse($probe['tls_peer_verification_disabled']);
            $this->assertSame($status, $probe['status'], json_encode($probe, JSON_THROW_ON_ERROR));
            $this->assertSame($retryable, $probe['retryable']);
            if ($outcome === 'rate') {
                $this->assertSame(120, $probe['retry_after']);
            }
            $successful = true;
        } finally {
            $this->cleanupWireFixture($directory, [$server, $client], $successful);
        }
    }

    public static function sessions(): array
    {
        return [
            ['brevo', 'accepted', 'trusted', 'api_accepted', false],
            ['sendgrid', 'accepted', 'trusted', 'api_accepted', false],
            ['brevo', 'rate', 'trusted', 'api_rejected', true],
            ['sendgrid', 'redirect', 'trusted', 'api_unconfirmed', false],
            ['brevo', 'oversized', 'trusted', 'api_unconfirmed', false],
            ['sendgrid', 'uncertain', 'trusted', 'api_unconfirmed', false],
            ['brevo', 'accepted', 'untrusted', 'api_unconfirmed', false],
            ['sendgrid', 'accepted', 'untrusted', 'api_unconfirmed', false],
        ];
    }
}
