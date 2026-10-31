<?php

namespace Tests\Unit\MailProviders;

use App\Enums\MailTransportMode;
use App\Services\MailProviders\BrevoApiTransport;
use App\Services\MailProviders\ProviderApiTransport;
use App\Services\MailProviders\ProviderCredentials;
use App\Services\MailProviders\SendGridApiTransport;
use Illuminate\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class ProviderApiTransportTest extends TestCase
{
    private const REFERENCE = '00000000-0000-4000-8000-000000000001';

    #[DataProvider('providers')]
    public function test_each_api_sends_its_own_schema_authentication_and_single_opaque_reference(string $provider): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $calls = 0;
        $factory->fake(function (Request $request, array $options) use ($provider, &$calls) {
            $calls++;
            $this->assertSame('POST', $request->method());
            $this->assertSame($provider === 'brevo' ? 'https://api.brevo.com/v3/smtp/email' : 'https://api.sendgrid.com/v3/mail/send', $request->url());
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertLessThanOrEqual(5., $options['timeout']);
            $this->assertLessThanOrEqual(2., $options['connect_timeout']);
            $data = $request->data();
            $this->assertSame('Prueba sintética', $data['subject']);
            if ($provider === 'brevo') {
                $this->assertTrue($request->hasHeader('api-key', 'synthetic-brevo-api-secret'));
                $this->assertFalse($request->hasHeader('Authorization'));
                $this->assertSame(['email' => 'sender@example.test', 'name' => 'Remitente'], $data['sender']);
                $this->assertSame([['email' => 'recipient@example.test']], $data['to']);
                $this->assertSame('Texto', $data['textContent']);
                $this->assertSame('<p>HTML</p>', $data['htmlContent']);
                $this->assertSame(['X-Mailin-custom' => 'emailing_reference:'.self::REFERENCE], $data['headers']);
                $this->assertSame(['email' => 'reply@example.test'], $data['replyTo']);

                return Factory::response(['messageId' => 'synthetic-brevo-message-id'], 201);
            }
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer synthetic-sendgrid-api-secret'));
            $this->assertFalse($request->hasHeader('api-key'));
            $this->assertSame(['email' => 'sender@example.test', 'name' => 'Remitente'], $data['from']);
            $this->assertSame([['to' => [['email' => 'recipient@example.test']]]], $data['personalizations']);
            $this->assertSame(['emailing_reference' => self::REFERENCE], $data['custom_args']);
            $this->assertSame([['type' => 'text/plain', 'value' => 'Texto'], ['type' => 'text/html', 'value' => '<p>HTML</p>']], $data['content']);
            $this->assertSame(['email' => 'reply@example.test'], $data['reply_to']);

            return Factory::response('', 202, ['X-Message-Id' => 'synthetic-sendgrid-message-id']);
        });
        $result = $this->transport($provider, $factory)->send($this->credentials($provider), $this->email(), self::REFERENCE);
        $this->assertSame('api_accepted', $result->status);
        $this->assertSame('synthetic-'.$provider.'-message-id', $result->providerMessageId);
        $this->assertFalse($result->retryable);
        $this->assertSame(1, $calls);
    }

    public static function providers(): array
    {
        return [['brevo'], ['sendgrid']];
    }

    #[DataProvider('errors')]
    public function test_status_is_classified_before_parsing_error_bodies_and_never_retried_implicitly(string $provider, int $status, string $expected, string $diagnostic, bool $retryable): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $calls = 0;
        $factory->fake(function () use ($status, &$calls) {
            $calls++;

            return Factory::response('<html>synthetic-sensitive-error</html>', $status, ['Retry-After' => '120', 'Content-Type' => 'text/html', 'Location' => 'https://untrusted.example.test/']);
        });
        $result = $this->transport($provider, $factory)->send($this->credentials($provider), $this->email(), self::REFERENCE);
        $this->assertSame($expected, $result->status);
        $this->assertSame($diagnostic, $result->diagnostic);
        $this->assertSame($retryable, $result->retryable);
        $this->assertSame($status === 429 ? 120 : null, $result->retryAfterSeconds);
        $this->assertStringNotContainsString('synthetic-sensitive-error', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertSame(1, $calls);
    }

    public static function errors(): array
    {
        $cases = [];
        foreach (['brevo', 'sendgrid'] as $provider) {
            foreach ([400, 401, 403, 408, 422, 429, 500, 502, 503, 302, 307] as $status) {
                $rejected = $status >= 400 && $status < 500 && $status !== 408;
                $cases[] = [$provider, $status, $rejected ? 'api_rejected' : 'api_unconfirmed',
                    $status === 429 ? 'api_rate_limited' : ($rejected ? (in_array($status, [401, 403], true) ? 'api_authentication_rejected' : 'api_payload_rejected') : 'api_unconfirmed'), $status === 429];
            }
        }

        return $cases;
    }

    #[DataProvider('providers')]
    public function test_a_connection_failure_is_ambiguous_and_does_not_expose_raw_vendor_details(string $provider): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $calls = 0;
        $factory->fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('synthetic-sensitive-error');
        });
        $result = $this->transport($provider, $factory)->send($this->credentials($provider), $this->email(), self::REFERENCE);
        $this->assertSame(['status' => 'api_unconfirmed', 'diagnostic' => 'api_unconfirmed'], $result->attributes());
        $this->assertFalse($result->retryable);
        $this->assertSame(1, $calls);
    }

    #[DataProvider('invalidReceipts')]
    public function test_missing_malformed_or_oversized_acceptance_receipts_stay_ambiguous(string $provider, string $body, array $headers): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $factory->fake(['*' => Factory::response($body, $provider === 'brevo' ? 201 : 202, $headers)]);
        $result = $this->transport($provider, $factory)->send($this->credentials($provider), $this->email(), self::REFERENCE);
        $this->assertSame('api_unconfirmed', $result->status);
        $this->assertFalse($result->retryable);
        $factory->assertSentCount(1);
    }

    public static function invalidReceipts(): array
    {
        return [
            ['brevo', 'not-json', ['Content-Type' => 'application/json']],
            ['brevo', '{}', ['Content-Type' => 'application/json']],
            ['brevo', '{"messageId":42}', ['Content-Type' => 'application/json']],
            ['brevo', '{"messageId":"synthetic-id"}', ['Content-Type' => 'text/html']],
            ['brevo', str_repeat('x', ProviderApiTransport::MAX_RESPONSE_BYTES + 1), ['Content-Type' => 'application/json']],
            ['sendgrid', '', []], ['sendgrid', '', ['X-Message-Id' => str_repeat('x', 256)]],
            ['sendgrid', str_repeat('x', ProviderApiTransport::MAX_RESPONSE_BYTES + 1), ['X-Message-Id' => 'synthetic-id']],
        ];
    }

    public function test_a_mismatched_profile_and_invalid_envelope_are_rejected_before_network(): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $factory->fake();
        $transport = $this->transport('brevo', $factory);
        $wrong = $transport->send($this->credentials('sendgrid'), $this->email(), self::REFERENCE);
        $invalid = $transport->send($this->credentials('brevo'), $this->email()->cc('other@example.test'), self::REFERENCE);
        $this->assertSame('api_rejected', $wrong->status);
        $this->assertSame('api_rejected', $invalid->status);
        $this->assertFalse($wrong->retryable);
        $factory->assertNothingSent();
    }

    #[DataProvider('retryHeaders')]
    public function test_retry_after_is_bounded_and_malformed_headers_do_not_trigger_fast_loops(string $header, int $seconds): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $factory->fake(['*' => Factory::response('not-json', 429, ['Retry-After' => $header])]);
        $result = $this->transport('brevo', $factory)->send($this->credentials('brevo'), $this->email(), self::REFERENCE);
        $this->assertSame('api_rate_limited', $result->diagnostic);
        $this->assertSame($seconds, $result->retryAfterSeconds);
        $factory->assertSentCount(1);
    }

    public static function retryHeaders(): array
    {
        return [['120', 120], ['0', 1], ['', 60], ['invalid', 60], ['-1', 60], ['99999999999999', 86400]];
    }

    public function test_a_large_request_is_rejected_without_opening_a_connection(): void
    {
        $factory = new Factory;
        $factory->preventStrayRequests();
        $factory->fake();
        $result = $this->transport('brevo', $factory)->send($this->credentials('brevo'), $this->email()->text(str_repeat('x', 4194304)), self::REFERENCE);
        $this->assertSame('api_payload_rejected', $result->diagnostic);
        $factory->assertNothingSent();
    }

    private function transport(string $provider, Factory $factory): ProviderApiTransport
    {
        $config = new Repository(['emailing' => ['provider_api_timeout' => 999]]);

        return $provider === 'brevo' ? new BrevoApiTransport($factory, $config) : new SendGridApiTransport($factory, $config);
    }

    private function credentials(string $provider): ProviderCredentials
    {
        return new ProviderCredentials($provider === 'brevo' ? MailTransportMode::BrevoApi : MailTransportMode::SendGridApi,
            apiKey: 'synthetic-'.$provider.'-api-secret');
    }

    private function email(): Email
    {
        return (new Email)->from('Remitente <sender@example.test>')->to('recipient@example.test')->replyTo('reply@example.test')
            ->subject('Prueba sintética')->text('Texto')->html('<p>HTML</p>');
    }
}
