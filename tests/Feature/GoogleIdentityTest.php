<?php

namespace Tests\Feature;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\ExternalIdentity;
use App\Models\IdentityAuditEvent;
use App\Models\User;
use App\Services\Auth\GoogleIdentityResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GoogleIdentityTest extends TestCase
{
    use DatabaseMigrations;

    private function identity(string $sub = 'synthetic-subject'): array
    {
        return ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => $sub, 'email' => 'provider@example.test'];
    }

    public function test_identity_is_exact_and_email_never_links_or_changes_local_user(): void
    {
        $user = User::factory()->create(['email' => 'provider@example.test']);
        $resolver = app(GoogleIdentityResolver::class);
        $this->assertNull($resolver->resolve($this->identity()));
        $linked = $resolver->link($user, $this->identity());
        $changed = $this->identity();
        $changed['email'] = 'changed@example.test';
        $this->assertTrue($user->is($resolver->resolve($changed)));
        $this->assertSame('provider@example.test', $user->fresh()->email);
        $this->assertSame($linked->id, $resolver->link($user, $changed)->id);
        $resolver->link($user, $this->identity('Synthetic-subject'));
        $this->assertDatabaseCount('external_identities', 2);
        // VARBINARY also distinguishes trailing bytes at the database constraint boundary.
        ExternalIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'issuer' => GoogleIdentityResolver::ISSUER, 'subject' => 'synthetic-subject ']);
        $this->assertDatabaseCount('external_identities', 3);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('identity_audit_events', 'account_id'));
        $this->assertStringNotContainsString('subject', IdentityAuditEvent::first()->toJson());
    }

    public function test_existing_owner_cannot_be_replaced_and_cross_user_unlink_is_rejected(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $resolver = app(GoogleIdentityResolver::class);
        $linked = $resolver->link($first, $this->identity());
        foreach ([fn () => $resolver->link($second, $this->identity()), fn () => $resolver->unlink($second, $linked->id)] as $action) {
            try {
                $action();
                $this->fail('Foreign identity operation accepted.');
            } catch (ValidationException) {
                $this->assertSame($first->id, $linked->fresh()->user_id);
            }
        }
    }

    public function test_random_hash_does_not_allow_unlink_but_password_reset_restores_local_method(): void
    {
        $user = User::factory()->create(['local_password_enabled' => false]);
        $resolver = app(GoogleIdentityResolver::class);
        $linked = $resolver->link($user, $this->identity());
        try {
            $resolver->unlink($user, $linked->id);
            $this->fail('Google-only identity removed.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('external_identities', 1);
        }
        app(ResetUserPassword::class)->reset($user, ['password' => 'Local-password-1234', 'password_confirmation' => 'Local-password-1234']);
        $resolver->unlink($user, $linked->id);
        $this->assertDatabaseCount('external_identities', 0);
        $this->assertSame(['linked', 'unlinked'], IdentityAuditEvent::pluck('action')->all());
    }

    public function test_audit_failure_rolls_back_link_and_unlink(): void
    {
        $user = User::factory()->create();
        $resolver = app(GoogleIdentityResolver::class);
        IdentityAuditEvent::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        try {
            $resolver->link($user, $this->identity());
            $this->fail('Audit failure ignored.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic audit failure', $error->getMessage());
        } finally {
            IdentityAuditEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('external_identities', 0);
        $this->assertDatabaseCount('identity_audit_events', 0);
        $linked = $resolver->link($user, $this->identity());
        IdentityAuditEvent::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        try {
            $resolver->unlink($user, $linked->id);
            $this->fail('Audit failure ignored.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('external_identities', 1);
            $this->assertDatabaseCount('identity_audit_events', 1);
        } finally {
            IdentityAuditEvent::flushEventListeners();
        }
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_real_parallel_link_by_two_users_has_exactly_one_owner_and_audit(): void
    {
        $users = User::factory()->count(2)->create();
        $results = $this->race([
            ['operation' => 'link', 'user_id' => $users[0]->id, 'sub' => 'race-subject'],
            ['operation' => 'link', 'user_id' => $users[1]->id, 'sub' => 'race-subject'],
        ]);
        sort($results);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertDatabaseCount('external_identities', 1);
        $this->assertDatabaseCount('identity_audit_events', 1);
    }

    public function test_real_parallel_same_user_links_are_idempotent_and_unlinks_serialize(): void
    {
        $user = User::factory()->create();
        $link = ['operation' => 'link', 'user_id' => $user->id, 'sub' => 'same-user-subject'];
        $this->assertSame(['accepted', 'accepted'], $this->race([$link, $link]));
        $this->assertDatabaseCount('external_identities', 1);
        $this->assertDatabaseCount('identity_audit_events', 1);
        $unlink = ['operation' => 'unlink', 'user_id' => $user->id, 'identity_id' => ExternalIdentity::sole()->id];
        $results = $this->race([$unlink, $unlink]);
        sort($results);
        $this->assertSame(['accepted', 'rejected'], $results);
        $this->assertDatabaseCount('external_identities', 0);
        $this->assertDatabaseCount('identity_audit_events', 2);
    }

    private function race(array $inputs): array
    {
        $directory = storage_path('app/private/google-race-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = [];
        try {
            foreach ($inputs as $index => $input) {
                $process = new Process([PHP_BINARY, base_path('tests/support/google-identity-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode($input + ['ready' => $directory.'/ready-'.$index, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Identity child processes did not reach SQL-guarded barrier.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/start', 'go');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Identity child failed: '.$process->getErrorOutput());
            }

            return array_map(fn (Process $process) => trim($process->getOutput()), $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
