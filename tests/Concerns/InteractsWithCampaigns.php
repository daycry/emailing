<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Models\Membership;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait InteractsWithCampaigns
{
    private Account $account;

    private User $owner;

    private EmailTemplate $template;

    private MailSender $sender;

    private Contact $contact;

    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->account = Account::create(['name' => 'Cuenta de campañas', 'owner_id' => $this->owner->id]);
        Membership::create(['account_id' => $this->account->id, 'user_id' => $this->owner->id, 'role' => 'admin', 'scopes' => []]);
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
        $this->template = EmailTemplate::create(['account_id' => $this->account->id, 'name' => 'Plantilla compartida', 'subject' => 'Hola {{ contact.name }}', 'html' => '<p>Hola {{ contact.name }} de {{ account.name }}</p>', 'text' => 'Hola {{ contact.email }}']);
        $this->sender = MailSender::create(['account_id' => $this->account->id, 'email' => 'sender@example.test', 'name' => 'Emisor', 'lab_authorized' => true]);
        $this->contact = $this->newContact();
        $this->service = app(CampaignService::class);
    }

    private function newContact(array $fields = []): Contact
    {
        return Contact::create($fields + ['account_id' => $this->account->id, 'email' => 'contact-'.Str::uuid().'@example.test', 'name' => 'Ana', 'consent_status' => 'granted', 'consent_source' => 'Formulario web', 'consented_at' => now('UTC')->subDay()]);
    }

    private function input(array $override = []): array
    {
        return array_replace(['name' => 'Noticias', 'template_id' => $this->template->id, 'sender_id' => $this->sender->id, 'list_ids' => [], 'contact_ids' => [$this->contact->id]], $override);
    }

    private function draft(): Campaign
    {
        return $this->service->save($this->account, $this->owner->id, null, null, $this->input());
    }

    private function reviewed(): Campaign
    {
        $campaign = $this->draft();

        return $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
    }

    private function authorize(Campaign $campaign, ?string $date = null): Campaign
    {
        return $this->service->authorizeCampaign($this->account, $this->owner->id, $campaign->id, $campaign->version, $campaign->reviewed_revision_id, true, $date);
    }

    private function member(array $scopes): Membership
    {
        return Membership::create(['account_id' => $this->account->id, 'user_id' => User::factory()->create()->id, 'role' => 'restricted', 'scopes' => $scopes]);
    }

    private function assertRejected(int $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail('La acción debía rechazarse con '.$code);
        } catch (HttpException $error) {
            $this->assertSame($code, $error->getStatusCode());
        }
    }
}
