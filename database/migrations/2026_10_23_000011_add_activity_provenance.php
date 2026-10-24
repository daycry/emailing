<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->uuid('delivery_id')->nullable();
            $table->unsignedInteger('delivery_generation')->nullable();
            $table->string('origin', 20)->default('unknown');
            // Attempts belong to the logical delivery. Account/campaign cleanup
            // must retain the existing cascade, rather than acquire a restriction.
            $table->foreign(['account_id', 'delivery_id'], 'activity_message_delivery_fk')
                ->references(['account_id', 'id'])->on('deliveries')->cascadeOnDelete();
            $table->unique(['delivery_id', 'delivery_generation'], 'activity_delivery_generation_once');
            $table->index(['account_id', 'created_at', 'id'], 'activity_message_window');
            $table->index(['account_id', 'status', 'created_at', 'id'], 'activity_message_status');
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->index(['account_id', 'created_at', 'id'], 'activity_delivery_window');
            $table->index(['account_id', 'status', 'created_at', 'id'], 'activity_delivery_status');
        });

        // A later generation does not prove the generation of an older attempt.
        // Only a unique, account-matching current pointer proves association.
        DB::statement(<<<'SQL'
            UPDATE mail_messages AS message_record
            INNER JOIN (
                SELECT account_id, mail_message_id, MIN(id) AS delivery_id
                FROM deliveries
                WHERE mail_message_id IS NOT NULL
                GROUP BY account_id, mail_message_id
                HAVING COUNT(*) = 1
            ) AS delivery_reference
                ON message_record.account_id = delivery_reference.account_id
                AND message_record.id = delivery_reference.mail_message_id
            SET message_record.delivery_id = delivery_reference.delivery_id,
                message_record.origin = 'campaign'
            SQL);
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropForeign('activity_message_delivery_fk');
            $table->dropUnique('activity_delivery_generation_once');
            $table->dropIndex('activity_message_window');
            $table->dropIndex('activity_message_status');
            $table->dropColumn(['delivery_id', 'delivery_generation', 'origin']);
        });
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('activity_delivery_window');
            $table->dropIndex('activity_delivery_status');
        });
    }
};
