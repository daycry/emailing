<?php

namespace App\Models;

use App\Enums\MailTransportMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MailProviderConnection extends Model
{
    use HasUuids;

    public const PROVIDERS = ['brevo', 'sendgrid'];

    public const SECRET_FIELDS = ['api_key', 'smtp_username', 'smtp_password', 'webhook_secret', 'webhook_public_key'];

    protected $guarded = ['id'];

    protected $hidden = self::SECRET_FIELDS;

    protected function casts(): array
    {
        return ['enabled' => 'boolean'] + array_fill_keys(self::SECRET_FIELDS, 'encrypted');
    }

    public function isConfiguredFor(MailTransportMode $mode): bool
    {
        if (! $this->enabled || $mode->provider() !== $this->provider) {
            return false;
        }
        $feedback = $this->provider === 'brevo' ? filled($this->webhook_secret) : filled($this->webhook_public_key);
        $credentials = $mode->isApi() ? filled($this->api_key)
            : filled($this->smtp_password) && ($this->provider === 'sendgrid' || filled($this->smtp_username));

        return $feedback && $credentials;
    }
}
