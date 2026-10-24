<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Like reviewed_revision_id, this is a revision identity retained across
            // reviews. Domain writes validate account/campaign under their mutexes.
            $table->unsignedBigInteger('materialized_revision_id')->nullable();
            $table->boolean('materialization_pending')->virtualAs('reviewed_revision_id IS NOT NULL AND (materialized_revision_id IS NULL OR materialized_revision_id <> reviewed_revision_id)');
        });

        // One upgrade pass over existing snapshots, rather than one historical
        // campaign per future trigger. Publication and SMTP state are irrelevant:
        // the initial durable delivery/outbox pair is the materialization boundary.
        DB::statement(<<<'SQL'
            UPDATE campaigns AS campaign
            JOIN campaign_revisions AS revision
              ON revision.id = campaign.reviewed_revision_id
             AND revision.account_id = campaign.account_id
             AND revision.campaign_id = campaign.id
            SET campaign.materialized_revision_id = revision.id
            WHERE revision.recipient_count = (
                SELECT COUNT(*) FROM campaign_recipients AS snapshot
                WHERE snapshot.account_id = campaign.account_id
                  AND snapshot.campaign_revision_id = revision.id
            ) AND NOT EXISTS (
                SELECT 1 FROM campaign_recipients AS recipient
                WHERE recipient.account_id = campaign.account_id
                  AND recipient.campaign_revision_id = revision.id
                  AND NOT EXISTS (
                      SELECT 1 FROM deliveries AS delivery
                      JOIN delivery_outbox AS event
                        ON event.account_id = delivery.account_id
                       AND event.delivery_id = delivery.id
                       AND event.generation = 1
                       AND event.type = 'delivery.ready'
                      WHERE delivery.account_id = recipient.account_id
                        AND delivery.campaign_id = campaign.id
                        AND delivery.campaign_revision_id = revision.id
                        AND delivery.campaign_recipient_id = recipient.id
                        AND delivery.source_hash = revision.source_hash
                  )
            )
            SQL);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->index(['materialization_pending', 'status', 'effective_at', 'id'], 'campaign_materialization_due');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaign_materialization_due');
            $table->dropColumn('materialization_pending');
            $table->dropColumn('materialized_revision_id');
        });
    }
};
