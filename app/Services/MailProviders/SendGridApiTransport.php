<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;
use Illuminate\Http\Client\Response;
use Symfony\Component\Mime\Email;

final class SendGridApiTransport extends ProviderApiTransport
{
    protected function mode(): MailTransportMode
    {
        return MailTransportMode::SendGridApi;
    }

    protected function acceptedStatus(): int
    {
        return 202;
    }

    protected function headers(ProviderCredentials $credentials): array
    {
        return ['Authorization' => 'Bearer '.$credentials->apiKey()];
    }

    protected function payload(Email $email, string $reference): array
    {
        $payload = ['from' => $this->mailbox($email->getFrom()[0]),
            'personalizations' => [['to' => [$this->mailbox($email->getTo()[0])]]],
            'subject' => $email->getSubject(), 'custom_args' => ['emailing_reference' => $reference], 'content' => []];
        if (($email->getTextBody() ?? '') !== '') {
            $payload['content'][] = ['type' => 'text/plain', 'value' => $email->getTextBody()];
        }
        if (($email->getHtmlBody() ?? '') !== '') {
            $payload['content'][] = ['type' => 'text/html', 'value' => $email->getHtmlBody()];
        }
        if ($email->getReplyTo() !== []) {
            $payload['reply_to'] = $this->mailbox($email->getReplyTo()[0]);
        }

        return $payload;
    }

    protected function receipt(Response $response, string $body): ?string
    {
        $values = $response->toPsrResponse()->getHeader('X-Message-Id');

        return count($values) === 1 ? $values[0] : null;
    }
}
