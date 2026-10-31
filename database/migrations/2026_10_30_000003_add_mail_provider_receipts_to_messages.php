<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One MySQL DDL statement: a failed index must not leave only the columns applied.
        DB::statement('ALTER TABLE mail_messages
            ADD COLUMN provider_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
            ADD COLUMN provider_message_id VARCHAR(255) NULL,
            ADD COLUMN provider_status VARCHAR(30) NULL,
            ADD COLUMN provider_status_at DATETIME(6) NULL,
            ADD UNIQUE KEY mail_messages_provider_reference_unique (provider_reference),
            ADD UNIQUE KEY mail_messages_account_id_id_unique (account_id, id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE mail_messages
            DROP INDEX mail_messages_provider_reference_unique,
            DROP INDEX mail_messages_account_id_id_unique,
            DROP COLUMN provider_reference,
            DROP COLUMN provider_message_id,
            DROP COLUMN provider_status,
            DROP COLUMN provider_status_at');
    }
};
