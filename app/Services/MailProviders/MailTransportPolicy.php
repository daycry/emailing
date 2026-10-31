<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;
use App\Models\MailProviderConnection;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Support\MailTransportConfiguration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;

final class MailTransportPolicy
{
    public static function senderAuthorizationColumn(string $mode): string
    {
        return MailTransportMode::tryFrom($mode)?->isExternal() ? 'external_authorized' : 'lab_authorized';
    }

    public static function eligibleSenders(int $accountId): Builder
    {
        $mode = MailSetting::where('account_id', $accountId)->value('mode') ?? 'log';

        return MailSender::where('account_id', $accountId)->where(self::senderAuthorizationColumn($mode), true);
    }

    public static function allowsSender(?MailSender $sender, string $mode): bool
    {
        return $sender !== null && (bool) $sender->{self::senderAuthorizationColumn($mode)};
    }

    /** Resolve under the account mutex; the result is never put on a queue. */
    public function resolve(int $accountId, string $mode): MailTransportConfiguration
    {
        $channel = MailTransportMode::tryFrom($mode);
        if ($channel === null || ($channel->isExternal() && ! config('emailing.external_enabled'))) {
            return new MailTransportConfiguration($channel, blockedReason: 'external_not_enabled');
        }
        if (! $channel->isExternal()) {
            return new MailTransportConfiguration($channel);
        }
        $origin = parse_url((string) config('app.url'));
        if (! is_array($origin) || ($origin['scheme'] ?? null) !== 'https' || empty($origin['host'])
            || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment'])) {
            return new MailTransportConfiguration($channel, blockedReason: 'provider_not_configured');
        }
        $connection = MailProviderConnection::where('account_id', $accountId)->where('provider', $channel->provider())->lockForUpdate()->first();
        try {
            if (! $connection || ! $connection->isConfiguredFor($channel)) {
                return new MailTransportConfiguration($channel, blockedReason: 'provider_not_configured');
            }
            $credentials = new ProviderCredentials($channel,
                apiKey: $channel->isApi() ? $connection->api_key : '',
                smtpUsername: $channel->provider() === 'brevo' && ! $channel->isApi() ? $connection->smtp_username : '',
                smtpPassword: ! $channel->isApi() ? $connection->smtp_password : '',
                smtpSecurity: $connection->smtp_security);

            return new MailTransportConfiguration($channel, $credentials);
        } catch (DecryptException|\InvalidArgumentException) {
            return new MailTransportConfiguration($channel, blockedReason: 'provider_credentials_unavailable');
        }
    }
}
