<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            // Provenance references may outlive a deleted source template or list.
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->json('list_ids');
            $table->json('contact_ids');
            $table->unsignedBigInteger('reviewed_revision_id')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('authorized_at')->nullable();
            $table->dateTime('effective_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'id']);
            $table->index(['status', 'effective_at']);
        });
        Schema::create('campaign_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedInteger('version');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('account_name');
            $table->string('subject', 200);
            $table->mediumText('html');
            $table->mediumText('text');
            $table->json('sender_snapshot');
            $table->char('source_hash', 64);
            $table->unsignedInteger('recipient_count');
            $table->unsignedInteger('excluded_count');
            $table->dateTime('created_at');
            $table->unique(['account_id', 'id']);
            $table->unique(['campaign_id', 'version']);
            $table->foreign(['account_id', 'campaign_id'])->references(['account_id', 'id'])->on('campaigns')->cascadeOnDelete();
        });
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('campaign_revision_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('email', 254)->collation('utf8mb4_bin');
            $table->string('name', 150);
            $table->string('consent_source', 255);
            $table->dateTime('consented_at');
            $table->unique(['campaign_revision_id', 'email']);
            $table->unique(['campaign_revision_id', 'contact_id']);
            $table->foreign(['account_id', 'campaign_revision_id'])->references(['account_id', 'id'])->on('campaign_revisions')->cascadeOnDelete();
            $table->foreign(['account_id', 'contact_id'])->references(['account_id', 'id'])->on('contacts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaign_revisions');
        Schema::dropIfExists('campaigns');
    }
};
