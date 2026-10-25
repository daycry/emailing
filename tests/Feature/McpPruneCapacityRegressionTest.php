<?php

namespace Tests\Feature;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class McpPruneCapacityRegressionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_scheduled_finite_batches_drain_a_ten_connection_day_of_windows_without_shortening_retention(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        try {
            $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => preg_match('/\bmcp:prune\b/', $event->command ?? '') === 1));
            $this->assertCount(1, $events);
            $event = $events[0];
            $this->assertTrue($event->withoutOverlapping);
            $options = preg_match('/--limit(?:=|\s+)([0-9]+)/', $event->command, $matches) ? ['--limit' => $matches[1]] : [];
            $batch = (int) ($options['--limit'] ?? 100);
            $this->assertGreaterThan(0, $batch);
            $this->assertLessThanOrEqual(1000, $batch);
            // Ten connections, one call/minute during eight hours, four windows/call.
            // The command must drain this retained backlog in at most one hour,
            // with each invocation still bounded by the command's 1000-row ceiling.
            $expiredCount = 10 * 8 * 60 * 4;
            $retainedCount = 500;
            $rows = [];
            for ($i = 0; $i < $expiredCount + $retainedCount; $i++) {
                $rows[] = ['key' => hash('sha256', 'synthetic-prune-backlog-'.$i), 'hits' => 1, 'expires_at' => $i < $expiredCount ? now()->subHours(49) : ($i % 2 === 0 ? now()->subHours(47) : now()->addMinute())];
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('mcp_admission_windows')->insert($chunk);
            }
            $cron = new CronExpression($event->expression);
            $runsPerDay = 0;
            $runsInHour = 0;
            for ($minute = 0; $minute < 1440; $minute++) {
                if ($cron->isDue(now()->startOfDay()->addMinutes($minute), $event->timezone ?? config('app.timezone'))) {
                    $runsPerDay++;
                    if ($minute >= 12 * 60 && $minute < 13 * 60) {
                        $runsInHour++;
                    }
                }
            }
            $remaining = $expiredCount;
            $removedPerPass = [];
            $passes = (int) ceil($expiredCount / 1000);
            for ($pass = 0; $pass < $passes; $pass++) {
                $this->artisan('mcp:prune', $options)->assertSuccessful();
                $current = DB::table('mcp_admission_windows')->where('expires_at', '<=', now()->subHours(48))->count();
                $removedPerPass[] = $remaining - $current;
                $this->assertGreaterThan(0, $remaining - $current);
                $this->assertLessThanOrEqual($batch, $remaining - $current);
                $remaining = $current;
                $this->assertSame($retainedCount, DB::table('mcp_admission_windows')->where('expires_at', '>', now()->subHours(48))->count());
            }
            if ($directory = getenv('MCP_EVIDENCE_DIRECTORY')) {
                file_put_contents($directory.'/prune-capacity.json', json_encode(['expired_fixture' => $expiredCount, 'retained_fixture' => $retainedCount, 'scheduled_batch' => $batch, 'scheduled_runs_per_day' => $runsPerDay, 'scheduled_runs_in_hour' => $runsInHour, 'passes' => $passes, 'removed_per_pass' => $removedPerPass, 'expired_remaining' => $remaining], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
            $this->assertSame(0, $remaining, 'Scheduled batch size leaves a growing admission-window backlog.');
            $this->assertGreaterThanOrEqual($expiredCount, $runsPerDay * $batch, 'Scheduled daily capacity cannot sustain the declared fixture.');
            $this->assertGreaterThanOrEqual($passes, $runsInHour, 'The declared backlog cannot drain within an hour of scheduled passes.');
            $this->assertSame($retainedCount, DB::table('mcp_admission_windows')->count());
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            $this->travelBack();
        }
    }
}
