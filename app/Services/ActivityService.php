<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\MailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityService
{
    public const STATES = ['pending' => 'Pendiente', 'reserved' => 'Reservada', 'sending' => 'En proceso', 'smtp_accepted' => 'Aceptado por SMTP', 'logged' => 'Archivo privado (log)', 'failed' => 'Fallido', 'suppressed' => 'Suprimido', 'ambiguous' => 'Aceptación ambigua', 'blocked' => 'Bloqueado'];

    public const POPULATIONS = ['api' => 'Entregas lógicas de API', 'campaign' => 'Entregas lógicas de campañas', 'test' => 'Pruebas explícitas', 'unknown' => 'Históricos de procedencia desconocida'];

    private const DIAGNOSTICS = [
        'quota_wait' => 'En espera de cuota', 'campaign_paused' => 'En espera de reanudación', 'campaign_future' => 'En espera de la fecha programada',
        'recipient_suppressed' => 'Destinatario suprimido', 'consent_not_current' => 'Sin consentimiento vigente', 'original_contact_changed' => 'El contacto original cambió',
        'source_not_authorized' => 'Origen sin autorización vigente', 'campaign_not_authorized' => 'Campaña sin autorización vigente', 'sender_or_transport_changed' => 'Remitente o transporte cambiado',
        'external_not_enabled' => 'Correo externo deshabilitado', 'attempts_exhausted' => 'Intentos agotados', 'artifact_failed' => 'Fallo al guardar el archivo privado',
        'smtp_unconfirmed' => 'Aceptación SMTP no confirmada; no reenviar automáticamente', 'sending_lease_expired' => 'Resultado de transporte no confirmado tras perder la reserva',
        'reservation_expired' => 'Reserva vencida antes del transporte', 'smtp_before_terminator' => 'Fallo demostrado anterior al final de DATA',
        'smtp_negative_greeting' => 'Rechazo SMTP en saludo', 'smtp_negative_helo' => 'Rechazo SMTP en saludo', 'smtp_negative_mail' => 'Rechazo SMTP del remitente',
        'smtp_negative_recipient' => 'Rechazo SMTP del destinatario', 'smtp_negative_data' => 'Rechazo SMTP de DATA', 'smtp_negative_data_final' => 'Rechazo SMTP final', 'smtp_negative_session' => 'Rechazo SMTP de sesión',
    ];

    public function __construct(private AccountContext $context) {}

    public function account(?int $expected = null): int
    {
        $id = $this->context->authorize('activity.read')->account_id;
        abort_unless($expected === null || $id === $expected, 409, 'La cuenta activa cambió. Vuelve a abrir Actividad.');

        return $id;
    }

    public static function diagnostic(?string $code): ?string
    {
        return $code === null ? null : (self::DIAGNOSTICS[$code] ?? 'Diagnóstico no reconocido; consulta la operación autorizada.');
    }

    public static function diagnosticCode(?string $code): ?string
    {
        return $code !== null && array_key_exists($code, self::DIAGNOSTICS) ? $code : null;
    }

    public function filters(array $input, ?int $expected = null): array
    {
        $account = $this->account($expected);
        $today = CarbonImmutable::now('UTC')->format('Y-m-d');
        $data = Validator::make($input + ['from' => CarbonImmutable::now('UTC')->subDays(29)->format('Y-m-d'), 'to' => $today, 'population' => '', 'status' => '', 'search' => '', 'campaign' => ''], [
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:to'], 'to' => ['required', 'date_format:Y-m-d', 'before_or_equal:9998-12-31'],
            'population' => ['present', 'nullable', Rule::in(array_keys(self::POPULATIONS))],
            'status' => ['present', 'nullable', Rule::in(array_keys(self::STATES))], 'search' => ['present', 'nullable', 'string', 'max:200'],
            'campaign' => ['present', 'nullable', 'integer', 'min:1'],
        ])->validate();
        if (! empty($data['campaign'])) {
            Campaign::where('account_id', $account)->findOrFail($data['campaign']);
        }

        return $data;
    }

    /** Every branch is scoped before UNION, including all provenance joins. */
    public function query(array $filters = [], ?int $expected = null, ?string $recordKey = null): Builder
    {
        $account = $this->account($expected);
        $filters = $this->filters($filters, $account);
        $start = $filters['from'].' 00:00:00';
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'UTC')->addDay()->format('Y-m-d').' 00:00:00';
        $deliveries = DB::table('deliveries as d')
            ->join('campaigns as c', fn ($join) => $join->on('c.id', '=', 'd.campaign_id')->on('c.account_id', '=', 'd.account_id'))
            ->join('campaign_revisions as r', fn ($join) => $join->on('r.id', '=', 'd.campaign_revision_id')->on('r.campaign_id', '=', 'd.campaign_id')->on('r.account_id', '=', 'd.account_id'))
            ->join('campaign_recipients as p', fn ($join) => $join->on('p.id', '=', 'd.campaign_recipient_id')->on('p.campaign_revision_id', '=', 'r.id')->on('p.account_id', '=', 'd.account_id'))
            ->leftJoin('mail_messages as current_message', fn ($join) => $join->on('current_message.id', '=', 'd.mail_message_id')->on('current_message.account_id', '=', 'd.account_id')->on('current_message.delivery_id', '=', 'd.id'))
            ->leftJoin('users as authorizer', 'authorizer.id', '=', 'c.authorized_by')
            ->where('d.account_id', $account)->where('d.created_at', '>=', $start)->where('d.created_at', '<', $end)
            ->selectRaw("CONCAT('delivery:', d.id) AS record_key, 'campaign' AS population, d.created_at AS occurred_at, d.status, d.diagnostic, p.email AS recipient, COALESCE(current_message.subject, r.subject) AS subject, COALESCE(current_message.sender_email, JSON_UNQUOTE(JSON_EXTRACT(r.sender_snapshot, '$.email'))) AS sender_email, c.id AS campaign_id, c.name AS campaign_name, authorizer.name AS actor_name, c.authorized_by AS actor_id, d.attempts, d.generation, d.available_at, current_message.mode, current_message.id AS artifact_message_id, (current_message.artifact_path IS NOT NULL) AS has_artifact, NULL AS api_key_id, NULL AS api_key_name, NULL AS api_request_id");
        if (! empty($filters['campaign'])) {
            $deliveries->where('d.campaign_id', $filters['campaign']);
        }
        if (! empty($filters['status'])) {
            $deliveries->where('d.status', $filters['status']);
        }
        $api = DB::table('deliveries as d')
            ->join('api_mail_requests as r', fn ($join) => $join->on('r.id', '=', 'd.api_request_id')->on('r.account_id', '=', 'd.account_id'))
            ->join('api_mail_recipients as p', fn ($join) => $join->on('p.id', '=', 'd.api_recipient_id')->on('p.api_request_id', '=', 'r.id')->on('p.account_id', '=', 'd.account_id'))
            ->join('account_api_keys as k', fn ($join) => $join->on('k.id', '=', 'r.credential_id')->on('k.account_id', '=', 'd.account_id'))
            ->leftJoin('mail_messages as current_message', fn ($join) => $join->on('current_message.id', '=', 'd.mail_message_id')->on('current_message.account_id', '=', 'd.account_id')->on('current_message.delivery_id', '=', 'd.id'))
            ->where('d.account_id', $account)->where('d.created_at', '>=', $start)->where('d.created_at', '<', $end)
            ->selectRaw("CONCAT('delivery:', d.id) AS record_key, 'api' AS population, d.created_at AS occurred_at, d.status, d.diagnostic, p.email AS recipient, p.subject, JSON_UNQUOTE(JSON_EXTRACT(r.sender_snapshot, '$.email')) AS sender_email, NULL AS campaign_id, NULL AS campaign_name, NULL AS actor_name, NULL AS actor_id, d.attempts, d.generation, d.available_at, current_message.mode, current_message.id AS artifact_message_id, (current_message.artifact_path IS NOT NULL) AS has_artifact, k.public_id AS api_key_id, k.name AS api_key_name, r.id AS api_request_id");
        if (! empty($filters['campaign'])) {
            $api->whereRaw('1 = 0');
        }
        if (! empty($filters['status'])) {
            $api->where('d.status', $filters['status']);
        }
        $messages = DB::table('mail_messages as m')->leftJoin('users as actor', 'actor.id', '=', 'm.actor_id')
            ->where('m.account_id', $account)->whereNull('m.delivery_id')->where('m.created_at', '>=', $start)->where('m.created_at', '<', $end)
            ->selectRaw("CONCAT('message:', m.id) AS record_key, CASE WHEN m.origin = 'test' THEN 'test' ELSE 'unknown' END AS population, m.created_at AS occurred_at, CASE WHEN m.status IN ('smtp_rejected', 'local_failed') THEN 'failed' WHEN m.status = 'smtp_unconfirmed' THEN 'ambiguous' ELSE m.status END AS status, m.diagnostic, m.recipient, m.subject, m.sender_email, NULL AS campaign_id, NULL AS campaign_name, actor.name AS actor_name, m.actor_id, CASE WHEN m.transport_started_at IS NOT NULL THEN 1 WHEN m.origin = 'test' AND m.status IN ('blocked', 'suppressed') THEN 0 ELSE NULL END AS attempts, NULL AS generation, NULL AS available_at, m.mode, m.id AS artifact_message_id, (m.artifact_path IS NOT NULL) AS has_artifact, NULL AS api_key_id, NULL AS api_key_name, NULL AS api_request_id");
        if ($recordKey !== null) {
            abort_unless(preg_match('/^(delivery:[a-f0-9-]{36}|message:[1-9][0-9]{0,19})$/D', $recordKey), 404);
            [$kind, $id] = explode(':', $recordKey, 2);
            // A point lookup must not scan either account's historical union.
            if ($kind === 'delivery') {
                $deliveries->where('d.id', $id);
                $api->where('d.id', $id);
                $messages->whereRaw('1 = 0');
            } else {
                $messages->where('m.id', $id);
                $deliveries->whereRaw('1 = 0');
                $api->whereRaw('1 = 0');
            }
        }
        if (! empty($filters['campaign'])) {
            $messages->whereRaw('1 = 0');
        }
        if (! empty($filters['status'])) {
            $statuses = match ($filters['status']) {
                'failed' => ['failed', 'smtp_rejected', 'local_failed'], 'ambiguous' => ['ambiguous', 'smtp_unconfirmed'], default => [$filters['status']],
            };
            $messages->whereIn('m.status', $statuses);
        }
        $query = DB::query()->fromSub($deliveries->unionAll($api)->unionAll($messages), 'activity');
        if (! empty($filters['population'])) {
            $query->where('population', $filters['population']);
        }
        if ($filters['search'] !== null && $filters['search'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $query->where(fn ($q) => $q->whereRaw("recipient LIKE ? ESCAPE '!'", [$term])->orWhereRaw("subject LIKE ? ESCAPE '!'", [$term]));
        }

        return $query;
    }

    public function page(array $filters, int $expected): LengthAwarePaginator
    {
        return $this->query($filters, $expected)->orderByDesc('occurred_at')->orderByDesc('record_key')->paginate(25, ['*'], 'activityPage');
    }

    public function metrics(array $filters, int $expected): array
    {
        $rows = $this->query($filters, $expected)->selectRaw("population, status, COUNT(*) AS count, SUM(CASE WHEN status = 'pending' AND diagnostic = 'quota_wait' THEN 1 ELSE 0 END) AS quota_wait, SUM(CASE WHEN status = 'pending' AND diagnostic = 'campaign_paused' THEN 1 ELSE 0 END) AS paused")->groupBy('population', 'status')->get();
        $result = [];
        foreach (self::POPULATIONS as $population => $label) {
            $counts = $rows->where('population', $population)->pluck('count', 'status')->map(fn ($count) => (int) $count)->all();
            $denominator = array_sum($counts);
            $accepted = $counts['smtp_accepted'] ?? 0;
            $result[$population] = ['label' => $label, 'total' => $denominator, 'states' => $counts, 'smtp_accepted' => $accepted,
                'quota_wait' => (int) $rows->where('population', $population)->sum('quota_wait'), 'paused' => (int) $rows->where('population', $population)->sum('paused'),
                'acceptance_percentage' => $denominator === 0 ? null : round(100 * $accepted / $denominator, 2)];
        }

        return $result;
    }

    public function detail(string $key, int $expected): object
    {
        $this->account($expected);
        abort_unless(preg_match('/^(delivery:[a-f0-9-]{36}|message:[1-9][0-9]{0,19})$/D', $key), 404);

        // Detail is not hidden merely because a user changed a date/status filter.
        return $this->query(['from' => '1000-01-01', 'to' => '9998-12-31'], $expected, $key)->first() ?? abort(404);
    }

    public function attempts(string $key, int $expected): LengthAwarePaginator
    {
        $row = $this->detail($key, $expected);
        $query = MailMessage::query()->where('mail_messages.account_id', $expected)->leftJoin('users as actor', 'actor.id', '=', 'mail_messages.actor_id')->leftJoin('account_api_keys as credential', fn ($join) => $join->on('credential.id', '=', 'mail_messages.api_credential_id')->on('credential.account_id', '=', 'mail_messages.account_id'));
        if (in_array($row->population, ['campaign', 'api'], true)) {
            $query->where('delivery_id', substr($key, 9));
        } else {
            $query->where('mail_messages.id', substr($key, 8));
        }

        return $query->select(['mail_messages.id', 'mail_messages.created_at', 'delivery_generation', 'mail_messages.status', 'diagnostic', 'mode', 'actor.name as actor_name', 'mail_messages.api_request_id', 'credential.public_id as api_key_id', 'credential.name as api_key_name'])
            ->selectRaw('(artifact_path IS NOT NULL) AS has_artifact')->orderByDesc('mail_messages.id')->paginate(10, ['*'], 'attemptsPage');
    }

    public static function csvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s\p{Z}\x{FEFF}\x00-\x1f]*[=+@-]|^[\t\r\n]/u', $text) ? "'".$text : $text;
    }

    public function export(array $input): StreamedResponse
    {
        $account = $this->account();
        $filters = $this->filters($input, $account);

        return response()->streamDownload(function () use ($account, $filters) {
            $this->account($account);
            $output = fopen('php://output', 'wb');
            try {
                fputcsv($output, ['Referencia', 'Población', 'Fecha UTC', 'Estado', 'Diagnóstico depurado', 'Destinatario', 'Asunto', 'Remitente', 'Campaña', 'Actor registrado', 'Comienzos durables registrados', 'Registros independientes conservados', 'Ventana desde UTC (incluido)', 'Ventana hasta UTC (día incluido)', 'Fuente', 'Llave API', 'Nombre de llave API', 'Petición API'], ',', '"', '');
                $last = null;
                do {
                    $query = $this->query($filters, $account)->orderByDesc('occurred_at')->orderByDesc('record_key');
                    if ($last) {
                        $query->where(fn ($q) => $q->where('occurred_at', '<', $last->occurred_at)->orWhere(fn ($q) => $q->where('occurred_at', $last->occurred_at)->where('record_key', '<', $last->record_key)));
                    }
                    $rows = $query->limit(200)->get();
                    foreach ($rows as $row) {
                        fputcsv($output, array_map(self::csvCell(...), [$row->record_key, self::POPULATIONS[$row->population], $row->occurred_at, self::STATES[$row->status] ?? 'Estado desconocido', self::diagnostic($row->diagnostic), $row->recipient, $row->subject, $row->sender_email, $row->campaign_name, $row->population === 'api' ? 'Credencial de cuenta' : ($row->actor_name ?? 'Desconocido'), $row->attempts ?? 'Desconocidos', in_array($row->population, ['campaign', 'api'], true) ? null : 1, $filters['from'], $filters['to'], 'SQL local; estados actuales de la cohorte filtrada', $row->api_key_id, $row->api_key_name, $row->api_request_id]), ',', '"', '');
                    }
                    $last = $rows->last();
                } while ($rows->count() === 200);
            } finally {
                fclose($output);
            }
        }, 'actividad.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
