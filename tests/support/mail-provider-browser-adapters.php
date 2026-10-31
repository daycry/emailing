<?php

use App\Models\Delivery;
use App\Models\MailMessage;
use App\Models\MailSetting;
use App\Services\DeliveryLifecycle;
use App\Services\SmtpConnector;
use App\Services\SmtpProtocolObserver;
use App\Support\SmtpRejected;
use App\Support\SmtpUnconfirmed;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;

// Loaded only by the owned browser harness, never by application bootstrap.
if (getenv('PROVIDER_BROWSER_TEST') !== '1' || ! app()->environment('testing', 'local')) {
    throw new RuntimeException('Owned testing adapter required.');
}
$outcome = $_COOKIE['provider_fixture_outcome'] ?? getenv('PROVIDER_BROWSER_OUTCOME') ?: 'accept';
$recordCall = function (string $channel) {
    $account = MailMessage::where('status', 'sending')->orderByDesc('id')->value('account_id');
    file_put_contents(getenv('PROVIDER_BROWSER_DIRECTORY').'/io.private.jsonl', json_encode(['channel' => $channel, 'account_id' => $account], JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
};
Http::preventStrayRequests();
Http::fake(function ($request) use ($outcome, $recordCall) {
    if (! in_array($request->url(), ['https://api.brevo.com/v3/smtp/email', 'https://api.sendgrid.com/v3/mail/send'], true)) {
        throw new RuntimeException('Unexpected provider fixture endpoint.');
    }
    $recordCall('api');
    if ($outcome === 'change') {
        $reference = $request['headers']['X-Mailin-custom'] ?? '';
        $reference = str_starts_with($reference, 'emailing_reference:') ? substr($reference, strlen('emailing_reference:')) : '';
        $message = MailMessage::where('provider_reference', $reference)->firstOrFail();
        MailSetting::where('account_id', $message->account_id)->update(['mode' => 'sendgrid_api']);
    }
    if ($outcome === 'fence') {
        $message = MailMessage::where('status', 'sending')->whereNotNull('delivery_id')->sole();
        Delivery::whereKey($message->delivery_id)->update(['lease_expires_at' => now()->subMinute()]);
        app(DeliveryLifecycle::class)->recover();
    }
    if ($outcome === 'rate') {
        return Http::response('', 429, ['Retry-After' => '120']);
    }
    if ($outcome === 'permanent') {
        return Http::response('', 401);
    }
    if ($outcome === 'uncertain') {
        return Http::response('', 503);
    }

    return str_contains($request->url(), 'brevo.com') ? Http::response(['messageId' => 'synthetic-browser-receipt'], 201)
        : Http::response('', 202, ['X-Message-Id' => 'synthetic-browser-receipt']);
});
app()->bind(SmtpConnector::class, fn () => new class($outcome, $recordCall) extends SmtpConnector
{
    public function __construct(private string $outcome, private Closure $recordCall) {}

    public function send(array $config, Email $email, ?Closure $heartbeat = null): void
    {
        if (($config['host'] ?? '') === '127.0.0.1' && ($config['port'] ?? null) === 11025 && ($config['security'] ?? '') === 'plain') {
            ($this->recordCall)('mailpit');
            (new SmtpConnector(app(SmtpProtocolObserver::class)))->send($config, $email, $heartbeat);

            return;
        }
        if (! in_array($config['host'], ['smtp-relay.brevo.com', 'smtp.sendgrid.net'], true)) {
            throw new RuntimeException('Unexpected SMTP fixture endpoint.');
        }
        ($this->recordCall)('smtp');
        if ($this->outcome === 'permanent') {
            throw new SmtpRejected('recipient', 550);
        }
        if ($this->outcome === 'rate') {
            throw new SmtpRejected('recipient', 450);
        }
        if ($this->outcome === 'uncertain') {
            throw new SmtpUnconfirmed;
        }
    }
});
