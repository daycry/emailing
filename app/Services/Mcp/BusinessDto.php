<?php

namespace App\Services\Mcp;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Suppression;
use App\Services\ActivityService;
use Carbon\CarbonImmutable;

/** Whitelisted public business projections, independent of model serialization. */
final class BusinessDto
{
    public static function campaign(Campaign $campaign): array
    {
        return ['id' => (int) $campaign->id, 'name' => $campaign->name, 'status' => $campaign->status,
            'version' => (int) $campaign->version, 'reviewed_revision_id' => self::integer($campaign->reviewed_revision_id),
            'authorized_at' => self::date($campaign->authorized_at), 'effective_at' => self::date($campaign->effective_at),
            'created_at' => self::date($campaign->created_at), 'updated_at' => self::date($campaign->updated_at)];
    }

    public static function revision(CampaignRevision $revision): array
    {
        return ['id' => (int) $revision->id, 'version' => (int) $revision->version, 'created_at' => self::date($revision->created_at)];
    }

    public static function recipient(CampaignRecipient $recipient): array
    {
        return ['id' => (int) $recipient->id, 'contact_id' => (int) $recipient->contact_id,
            'email' => $recipient->email, 'name' => $recipient->name, 'consent_source' => $recipient->consent_source,
            'consented_at' => self::date($recipient->consented_at)];
    }

    public static function suppression(Suppression $suppression): array
    {
        return ['id' => (int) $suppression->id, 'normalized_email' => $suppression->normalized_email, 'source' => $suppression->source,
            'created_at' => self::date($suppression->created_at), 'updated_at' => self::date($suppression->updated_at)];
    }

    public static function activity(object $row): array
    {
        $diagnostic = ActivityService::diagnosticCode($row->diagnostic);

        return ['record_key' => $row->record_key, 'population' => $row->population,
            'occurred_at' => self::date($row->occurred_at), 'status' => $row->status,
            'recipient' => $row->recipient, 'subject' => $row->subject, 'sender_email' => $row->sender_email,
            'campaign_id' => self::integer($row->campaign_id), 'campaign_name' => $row->campaign_name,
            'durable_starts' => self::integer($row->attempts), 'generation' => self::integer($row->generation),
            'available_at' => self::date($row->available_at), 'diagnostic_code' => $diagnostic,
            'diagnostic_label' => ActivityService::diagnostic($diagnostic), 'transport_mode' => self::mode($row->mode),
            'actor_name' => $row->actor_name, 'request_id' => $row->api_request_id,
            'credential' => self::credential($row), 'has_private_artifact' => (bool) $row->has_artifact];
    }

    public static function attempt(object $row): array
    {
        $diagnostic = ActivityService::diagnosticCode($row->diagnostic);

        return ['id' => (int) $row->id, 'created_at' => self::date($row->created_at),
            'delivery_generation' => self::integer($row->delivery_generation), 'transport_status' => $row->status,
            'diagnostic_code' => $diagnostic, 'diagnostic_label' => ActivityService::diagnostic($diagnostic),
            'transport_mode' => self::mode($row->mode), 'actor_name' => $row->actor_name,
            'request_id' => $row->api_request_id, 'credential' => self::credential($row),
            'has_private_artifact' => (bool) $row->has_artifact];
    }

    public static function filters(array $filters): array
    {
        return ['from_utc' => $filters['from'], 'to_utc' => $filters['to'],
            'population' => $filters['population'] ?: null, 'status' => $filters['status'] ?: null,
            'campaign_id' => self::integer($filters['campaign']), 'search' => $filters['search']];
    }

    public static function metrics(array $metrics): array
    {
        $result = [];
        foreach (ActivityService::POPULATIONS as $population => $label) {
            $source = $metrics[$population];
            $states = [];
            foreach (ActivityService::STATES as $state => $stateLabel) {
                $states[$state] = (int) ($source['states'][$state] ?? 0);
            }
            $result[$population] = ['total' => (int) $source['total'], 'states' => $states,
                'smtp_accepted' => (int) $source['smtp_accepted'], 'quota_wait' => (int) $source['quota_wait'],
                'paused' => (int) $source['paused'], 'acceptance_percentage' => $source['acceptance_percentage']];
        }

        return $result;
    }

    public static function date(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    private static function integer(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function mode(?string $mode): ?string
    {
        return match ($mode) {
            'log' => 'log',
            'smtp', 'mailpit' => 'smtp',
            default => null,
        };
    }

    private static function credential(object $row): ?array
    {
        return $row->api_key_id === null ? null : ['kind' => 'api_key', 'public_id' => $row->api_key_id, 'name' => $row->api_key_name];
    }
}
