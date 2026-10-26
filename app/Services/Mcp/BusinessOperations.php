<?php

namespace App\Services\Mcp;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignRevision;
use App\Services\AccountContext;
use App\Services\ActivityService;
use App\Services\CampaignService;
use App\Services\Mcp\Tools\DataTool;
use App\Services\SuppressionService;
use App\Support\TemplatePreview;
use App\Support\TemplateRenderer;
use Carbon\CarbonImmutable;

/** Adapters over the shared business services; this layer never dispatches mail. */
final class BusinessOperations
{
    public const NAMES = [
        'activity_attempts', 'activity_list', 'activity_metrics',
        'campaigns_authorize', 'campaigns_create', 'campaigns_get', 'campaigns_list',
        'campaigns_pause', 'campaigns_resume', 'campaigns_review', 'campaigns_update',
        'suppressions_add', 'suppressions_list', 'suppressions_remove',
    ];

    public function run(DataTool $tool, DelegatedContext $context, array $arguments): array
    {
        $name = $tool->name();
        $account = Account::findOrFail($context->accountId());
        $actor = $context->actorId();
        $pages = app(DataPage::class);
        if (str_starts_with($name, 'activity_')) {
            return $this->activity($tool, $context, $arguments);
        }
        if (str_starts_with($name, 'suppressions_')) {
            $service = new SuppressionService($context);
            if ($name === 'suppressions_list') {
                return $pages->make($tool, $context, $service->suppressions($account, $actor), $arguments, [], BusinessDto::suppression(...));
            }
            if ($name === 'suppressions_add') {
                $suppression = $service->suppress($account, $actor, ['email' => $arguments['email']]);
                $this->reference('suppression', $suppression->id);

                return $this->result($context, ['suppression' => BusinessDto::suppression($suppression)]);
            }
            $service->remove($account, $actor, $arguments['suppression_id']);
            $this->reference('suppression', $arguments['suppression_id']);

            return $this->result($context, ['id' => $arguments['suppression_id'], 'deleted' => true]);
        }
        $service = new CampaignService(app(TemplateRenderer::class), $context);
        if ($name === 'campaigns_list') {
            return $pages->make($tool, $context, $service->campaigns($account, $actor), $arguments, [], BusinessDto::campaign(...));
        }
        if ($name === 'campaigns_get') {
            return $this->campaignResult($tool, $context, $service, $account, $arguments['campaign_id'], $arguments['include'] ?? []);
        }
        if ($name === 'campaigns_review') {
            if ($arguments['include_sender'] ?? false) {
                $context->authorize(['mail.send'], true);
            }
            $campaign = $service->review($account, $actor, $arguments['campaign_id'], $arguments['expected_version']);
            $this->reference('campaign', $campaign->id);

            return $this->campaignResult($tool, $context, $service, $account, $campaign->id,
                ['content' => true, 'sender' => $arguments['include_sender'] ?? false, 'preview' => true, 'recipients' => []]);
        }
        $campaign = match ($name) {
            'campaigns_create' => $service->save($account, $actor, null, null, $arguments['campaign']),
            'campaigns_update' => $service->save($account, $actor, $arguments['campaign_id'], $arguments['expected_version'], $arguments['campaign']),
            'campaigns_authorize' => $service->authorizeCampaign($account, $actor, $arguments['campaign_id'], $arguments['expected_version'], $arguments['revision_id'], $arguments['confirmed'], $arguments['effective_utc'] ?? null),
            'campaigns_pause' => $service->pause($account, $actor, $arguments['campaign_id'], $arguments['expected_version']),
            'campaigns_resume' => $service->resume($account, $actor, $arguments['campaign_id'], $arguments['expected_version']),
            default => throw new \LogicException('Unknown business operation'),
        };
        $this->reference('campaign', $campaign->id);

        return $this->result($context, ['campaign' => BusinessDto::campaign($campaign)]);
    }

    private function campaignResult(DataTool $tool, DelegatedContext $context, CampaignService $service, Account $account, int $id, array $include): array
    {
        $scopes = [];
        if ($include['composition'] ?? false) {
            $scopes = [...$scopes, 'contacts.read', 'templates.read'];
        }
        if ($include['content'] ?? false) {
            $scopes[] = 'templates.read';
        }
        if ($include['sender'] ?? false) {
            $scopes[] = 'mail.send';
        }
        if ($include['preview'] ?? false) {
            $scopes = [...$scopes, 'contacts.read', 'templates.read'];
        }
        $recipients = array_key_exists('recipients', $include);
        if ($recipients) {
            $scopes[] = 'contacts.read';
        }
        $context->authorize(array_values(array_unique($scopes)), true);
        $campaign = $service->campaign($account, $context->actorId(), $id);
        $filters = ['campaign_id' => (string) $campaign->id];
        $cursor = app(DataCursor::class);
        $recipientArguments = $include['recipients'] ?? [];
        $after = isset($recipientArguments['cursor'])
            ? $cursor->read($context, 'campaigns_get', $filters, $recipientArguments['cursor'], 'recipients', $campaign->reviewed_revision_id === null ? null : (string) $campaign->reviewed_revision_id)
            : null;
        $revision = $campaign->reviewed_revision_id === null ? null : $service->revision($account, $campaign);
        $data = ['campaign' => BusinessDto::campaign($campaign), 'revision' => $revision === null ? null : BusinessDto::revision($revision)];
        if ($include['composition'] ?? false) {
            $data['composition'] = ['template_id' => (int) $campaign->template_id, 'list_ids' => $campaign->list_ids, 'contact_ids' => $campaign->contact_ids];
        }
        if ($revision === null) {
            return $this->result($context, $data);
        }
        if ($include['content'] ?? false) {
            $data['revision']['content'] = $revision->only(['subject', 'html', 'text']);
        }
        if ($include['sender'] ?? false) {
            $snapshot = $revision->sender_snapshot;
            $data['revision']['sender'] = ['id' => (int) $snapshot['id'], 'email' => $snapshot['email'], 'name' => $snapshot['name'], 'reply_to' => $snapshot['reply_to']];
        }
        if ($include['preview'] ?? false) {
            $first = $service->recipients($account, $context->actorId(), $campaign, $revision)->firstOrFail();
            $preview = $service->renderContent($revision->only(['subject', 'html', 'text']),
                ['contact.name' => $first->name, 'contact.email' => $first->email, 'account.name' => $revision->account_name]);
            $preview['document'] = app(TemplatePreview::class)->document($preview['html']);
            if (strlen($preview['document']) > 2097152) {
                throw new DataFailure('result_too_large');
            }
            $data['revision']['preview'] = $preview;
        }
        if (! $recipients) {
            return $this->result($context, $data);
        }

        return $this->recipients($tool, $context, $service, $account, $campaign, $revision, $recipientArguments, $after, $filters, $data);
    }

    private function recipients(DataTool $tool, DelegatedContext $context, CampaignService $service, Account $account, Campaign $campaign, CampaignRevision $revision, array $arguments, ?array $after, array $filters, array $data): array
    {
        $query = $service->recipients($account, $context->actorId(), $campaign, $revision);
        if ($after !== null) {
            $query->where('id', '>', $after['position']['id']);
        }
        $limit = $arguments['limit'] ?? 25;
        $candidates = $query->limit($limit + 1)->get();
        $meta = DataDto::meta($context);
        $envelope = function (array $items, array $page) use ($data, $revision, $meta): array {
            $data['revision']['audience'] = ['recipient_count' => (int) $revision->recipient_count,
                'excluded_count' => (int) $revision->excluded_count, 'items' => $items, 'page' => $page];

            return ['data' => $data, 'meta' => $meta];
        };
        $issue = null;
        $continuation = function ($last) use ($context, $filters, $revision, &$issue): string {
            $issue ??= app(DataCursor::class)->issuer($context, 'campaigns_get', $filters, 'recipients', (string) $revision->id);

            return $issue(['kind' => 'recipient_id_asc', 'id' => (string) $last->id]);
        };

        return app(DataPage::class)->cut($tool, $candidates, $limit, BusinessDto::recipient(...), $envelope, $continuation);
    }

    private function activity(DataTool $tool, DelegatedContext $context, array $arguments): array
    {
        $service = new ActivityService(new AccountContext($context));
        $account = $context->accountId();
        if ($tool->name() === 'activity_attempts') {
            $key = $arguments['record_key'];

            return app(DataPage::class)->make($tool, $context, $service->attemptQuery($key, $account), $arguments,
                ['record_key' => $key], BusinessDto::attempt(...), ['record_key' => $key]);
        }
        $resolve = function (?array $window) use ($service, $arguments, $account): array {
            $input = $arguments['filters'] ?? [];
            if ($window !== null) {
                $input += ['from' => $window['from_utc'], 'to' => $window['to_utc']];
            }
            $filters = $service->filters($input, $account);
            if (! empty($filters['campaign'])) {
                $filters['campaign'] = (string) $filters['campaign'];
            }

            return $filters;
        };
        $cursor = app(DataCursor::class);
        $after = isset($arguments['cursor']) ? $cursor->read($context, 'activity_list', $resolve, $arguments['cursor']) : null;
        $filters = $after['filters'] ?? $resolve(null);
        if ($tool->name() === 'activity_metrics') {
            return $this->result($context, ['filters' => BusinessDto::filters($filters),
                'populations' => BusinessDto::metrics($service->metrics($filters, $account)), 'delivery_feedback' => 'not_available']);
        }
        $query = $service->query($filters, $account);
        if ($after !== null) {
            $position = $after['position'];
            $query->whereRaw('(occurred_at, record_key) < (?, ?)',
                [CarbonImmutable::parse($position['occurred_at'])->utc()->format('Y-m-d H:i:s.u'), $position['record_key']]);
        }
        $limit = $arguments['limit'] ?? 25;
        $candidates = $query->orderByDesc('occurred_at')->orderByDesc('record_key')->limit($limit + 1)->get();
        $meta = DataDto::meta($context);
        $issue = null;
        $continuation = function ($last) use ($cursor, $context, $filters, &$issue): string {
            $issue ??= $cursor->issuer($context, 'activity_list', $filters, 'root', null, ['from_utc' => $filters['from'], 'to_utc' => $filters['to']]);

            return $issue(['kind' => 'activity_desc', 'occurred_at' => BusinessDto::date($last->occurred_at), 'record_key' => $last->record_key]);
        };

        return app(DataPage::class)->cut($tool, $candidates, $limit, BusinessDto::activity(...),
            fn (array $items, array $page): array => ['data' => ['filters' => BusinessDto::filters($filters), 'items' => $items], 'meta' => $meta, 'page' => $page], $continuation);
    }

    private function result(DelegatedContext $context, array $data): array
    {
        return ['data' => $data, 'meta' => DataDto::meta($context)];
    }

    private function reference(string $kind, int $id): void
    {
        request()->attributes->set('mcp.reference', ['kind' => $kind, 'id' => (string) $id]);
    }
}
