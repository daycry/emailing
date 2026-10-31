<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;
use Illuminate\Http\Client\Response;
use Symfony\Component\Mime\Email;

final class BrevoApiTransport extends ProviderApiTransport
{
    protected function mode(): MailTransportMode
    {
        return MailTransportMode::BrevoApi;
    }

    protected function acceptedStatus(): int
    {
        return 201;
    }

    protected function headers(ProviderCredentials $credentials): array
    {
        return ['api-key' => $credentials->apiKey()];
    }

    protected function payload(Email $email, string $reference): array
    {
        $payload = ['sender' => $this->mailbox($email->getFrom()[0]), 'to' => [$this->mailbox($email->getTo()[0])],
            'subject' => $email->getSubject(), 'headers' => ['X-Mailin-custom' => 'emailing_reference:'.$reference]];
        if (($email->getTextBody() ?? '') !== '') {
            $payload['textContent'] = $email->getTextBody();
        }
        if (($email->getHtmlBody() ?? '') !== '') {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $this->mailbox($email->getReplyTo()[0]);
        }

        return $payload;
    }

    protected function receipt(Response $response, string $body): ?string
    {
        if (strtolower(trim(explode(';', $response->header('Content-Type'))[0])) !== 'application/json') {
            return null;
        }
        $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);

        return is_array($data) && is_string($data['messageId'] ?? null) ? $data['messageId'] : null;
    }
}
