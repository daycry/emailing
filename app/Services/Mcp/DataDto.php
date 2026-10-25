<?php

namespace App\Services\Mcp;

use Illuminate\Database\Eloquent\Model;

/** Explicit public projections; model serialization never defines an MCP response. */
final class DataDto
{
    public static function contact(Model $contact): array
    {
        return ['id' => (int) $contact->id, 'email' => $contact->email, 'name' => $contact->name,
            'consent_status' => $contact->consent_status, 'consent_source' => $contact->consent_source,
            'consented_at' => self::date($contact->consented_at)] + self::timestamps($contact);
    }

    public static function list(Model $list, bool $count = true): array
    {
        $dto = ['id' => (int) $list->id, 'name' => $list->name, 'description' => $list->description] + self::timestamps($list);
        if ($count) {
            $dto['contacts_count'] = (int) $list->contacts_count;
        }

        return $dto;
    }

    public static function template(Model $template, bool $content = true): array
    {
        $dto = ['id' => (int) $template->id, 'name' => $template->name, 'subject' => $template->subject] + self::timestamps($template);
        if ($content) {
            $dto += ['html' => $template->html, 'text' => $template->text];
        }

        return $dto;
    }

    public static function report(Model $report): array
    {
        return ['id' => (int) $report->id, 'counts' => ['total' => (int) $report->total,
            'imported' => (int) $report->imported, 'invalid' => (int) $report->invalid,
            'duplicates' => (int) $report->duplicates]] + self::timestamps($report);
    }

    public static function row(Model $row): array
    {
        return ['row_number' => (int) $row->row_number, 'email' => $row->email,
            'status' => $row->status, 'message' => $row->message];
    }

    public static function meta(DelegatedContext $context): array
    {
        return ['trace_id' => $context->correlationId(), 'observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')];
    }

    private static function timestamps(Model $model): array
    {
        return ['created_at' => self::date($model->created_at), 'updated_at' => self::date($model->updated_at)];
    }

    private static function date(mixed $value): ?string
    {
        return $value?->utc()->format('Y-m-d\TH:i:s.u\Z');
    }
}
