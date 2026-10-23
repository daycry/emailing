<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_revisions', function (Blueprint $table) {
            $table->unique(['account_id', 'campaign_id', 'id'], 'revision_provenance');
        });
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->unique(['account_id', 'campaign_revision_id', 'id'], 'recipient_provenance');
        });
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('campaign_revision_id');
            $table->unsignedBigInteger('campaign_recipient_id');
            $table->char('source_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('generation')->default(1);
            $table->timestamps();
            $table->unique(['account_id', 'id']);
            $table->unique(['campaign_revision_id', 'campaign_recipient_id'], 'delivery_recipient_once');
            $table->foreign(['account_id', 'campaign_id', 'campaign_revision_id'], 'delivery_revision_fk')->references(['account_id', 'campaign_id', 'id'])->on('campaign_revisions')->cascadeOnDelete();
            $table->foreign(['account_id', 'campaign_revision_id', 'campaign_recipient_id'], 'delivery_recipient_fk')->references(['account_id', 'campaign_revision_id', 'id'])->on('campaign_recipients')->cascadeOnDelete();
        });
        Schema::create('delivery_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->uuid('delivery_id');
            $table->unsignedInteger('generation');
            $table->string('type', 30)->default('delivery.ready');
            $table->string('status', 20)->default('pending');
            $table->dateTime('available_at', 6);
            $table->uuid('lease_token')->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->unsignedInteger('publish_attempts')->default(0);
            $table->string('last_error', 40)->nullable();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('eligible_at', 6)->nullable()->storedAs("CASE WHEN status = 'pending' THEN available_at WHEN status = 'publishing' THEN GREATEST(available_at, lease_expires_at) ELSE NULL END");
            $table->timestamps();
            $table->unique(['delivery_id', 'generation', 'type'], 'outbox_generation_once');
            $table->index(['eligible_at', 'id'], 'outbox_due');
            $table->foreign(['account_id', 'delivery_id'])->references(['account_id', 'id'])->on('deliveries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_outbox');
        Schema::dropIfExists('deliveries');
        // InnoDB may replace the original implicit child-FK index with our
        // covering unique index. Restore a supporting index before removing it.
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (! Schema::hasIndex('campaign_recipients', 'recipient_parent_lookup')) {
                $table->index(['account_id', 'campaign_revision_id'], 'recipient_parent_lookup');
            }
            $table->dropUnique('recipient_provenance');
        });
        Schema::table('campaign_revisions', function (Blueprint $table) {
            if (! Schema::hasIndex('campaign_revisions', 'revision_parent_lookup')) {
                $table->index(['account_id', 'campaign_id'], 'revision_parent_lookup');
            }
            $table->dropUnique('revision_provenance');
        });
    }
};
