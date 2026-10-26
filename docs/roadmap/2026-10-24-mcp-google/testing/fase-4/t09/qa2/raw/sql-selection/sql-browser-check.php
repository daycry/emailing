<?php

// Private read-only check of this run's recorded resources; never a global cleanup.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
try {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    App\Support\TestingDatabaseGuard::assertSafe();
    $directory = realpath($argv[1] ?? '');
    $parent = realpath(dirname(__DIR__).'/mcp-google-phase3');
    if ($directory === false || dirname($directory) !== $parent || ! preg_match('/\Abrowser-[a-f0-9-]{36}\z/D', basename($directory))) {
        throw new RuntimeException('Owned browser directory required');
    }
    $resources = json_decode(file_get_contents($directory.'/cleanup-resources.json'), true, flags: JSON_THROW_ON_ERROR);
    $owned = json_decode(file_get_contents($directory.'/google-owned-rows.json'), true, flags: JSON_THROW_ON_ERROR);
    $checks = [
        'users' => ['users', 'id', $resources['users']],
        'accounts' => ['accounts', 'id', $resources['accounts']],
        'clients' => ['mcp_clients', 'id', $resources['clients']],
        'grants' => ['mcp_grants', 'id', $resources['grants']],
        'tokens' => ['personal_access_tokens', 'id', $resources['tokens']],
        'sessions' => ['mcp_sessions', 'token_id', $resources['tokens']],
        'authorization_codes' => ['mcp_authorization_codes', 'grant_id', $resources['grants']],
        'authorization_requests' => ['mcp_authorization_requests', 'client_id', $resources['clients']],
        'mcp_audit' => ['mcp_audit_events', 'actor_id', $resources['users']],
        'google_attempts' => ['google_login_attempts', 'id', $owned['attempts'] ?? []],
        'google_codes' => ['google_login_codes', 'code_hash', $owned['codes'] ?? []],
        'admission_windows' => ['mcp_admission_windows', 'key', $owned['windows'] ?? []],
    ];
    foreach (['contacts', 'contact_lists', 'contact_list_memberships', 'contact_imports', 'contact_import_rows', 'email_templates', 'audit_events', 'mail_messages', 'delivery_outbox', 'mail_senders', 'mail_settings', 'campaigns', 'campaign_revisions', 'campaign_recipients', 'suppressions', 'deliveries'] as $table) {
        $checks[$table] = [$table, 'account_id', $resources['accounts']];
    }
    $counts = [];
    foreach ($checks as $label => [$table, $column, $values]) {
        $counts[$label] = Illuminate\Support\Facades\DB::table($table)->whereIn($column, $values)->count();
    }
    $level = Illuminate\Support\Facades\DB::transactionLevel();
    echo json_encode(['testing_database_guard' => 'passed', 'transaction_level' => $level,
        'owned_fixture_rows_remaining' => $counts, 'operational_database_queried' => false,
        'data_mutated_by_this_check' => false], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(array_sum($counts) === 0 && $level === 0 ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['error_class' => get_class($error), 'error_code' => $error->getCode()], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
