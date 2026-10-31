<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderSchemaTest extends TestCase
{
    use DatabaseMigrations, MailProviderFixtures;

    public function test_expanded_mysql_schema_preserves_legacy_writers_and_separates_feedback_from_acceptance(): void
    {
        [, $account] = $this->providerOwner();
        $sender = $this->providerSender($account);
        $base = ['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'mode' => 'log',
            'status' => 'logged', 'sender_email' => $sender->email, 'recipient' => 'recipient@example.test', 'subject' => 'Schema'];
        $first = MailMessage::create($base);
        $second = MailMessage::create($base);
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($first->fresh()->provider_reference);
        foreach (['provider_reference', 'provider_message_id', 'provider_status', 'provider_status_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('mail_messages', $column));
        }
        $reference = (string) Str::uuid();
        $first->update(['mode' => 'brevo_api', 'status' => 'api_accepted', 'provider_reference' => $reference,
            'provider_message_id' => 'synthetic-provider-id']);
        $first->update(['provider_status' => 'delivered', 'provider_status_at' => '2030-01-02 03:04:05.123456']);
        $this->assertSame('api_accepted', $first->fresh()->status);
        $this->assertSame('2030-01-02 03:04:05.123456', $first->fresh()->provider_status_at->format('Y-m-d H:i:s.u'));
        $this->assertArrayNotHasKey('provider_reference', $first->fresh()->toArray());
        $this->assertArrayNotHasKey('provider_message_id', $first->fresh()->toArray());
        $index = DB::select("SELECT COLUMN_NAME AS column_name, NON_UNIQUE AS non_unique FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mail_messages' AND INDEX_NAME = 'mail_messages_account_id_id_unique'
            ORDER BY SEQ_IN_INDEX");
        $this->assertSame(['account_id', 'id'], array_column($index, 'column_name'));
        $this->assertSame([0, 0], array_map(fn ($row) => (int) $row->non_unique, $index));
        $this->expectException(QueryException::class);
        $second->update(['provider_reference' => $reference]);
    }
}
