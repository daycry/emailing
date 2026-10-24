<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_api_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name', 150);
            $table->string('token', 64)->unique();
            $table->json('abilities');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('granted_by_key_id')->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->dateTime('last_used_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['account_id', 'id']);
            $table->foreign(['account_id', 'granted_by_key_id'], 'api_key_parent_fk')->references(['account_id', 'id'])->on('account_api_keys')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE account_api_keys ADD CONSTRAINT api_key_owner_check CHECK (tokenable_type = 'App\\\\Models\\\\Account' AND tokenable_id = account_id)");
        Schema::create('api_admission_windows', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Zero denotes the account-wide counter, internal key IDs are positive.
            $table->unsignedBigInteger('credential_id');
            $table->dateTime('starts_at');
            $table->unsignedInteger('used')->default(0);
            $table->primary(['account_id', 'credential_id', 'starts_at'], 'api_window_identity');
        });
        Schema::create('api_mail_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('credential_id');
            $table->char('idempotency_hash', 64)->collation('ascii_bin');
            $table->unsignedSmallInteger('fingerprint_version')->default(1);
            $table->char('fingerprint', 64);
            $table->char('source_hash', 64);
            $table->json('sender_snapshot');
            $table->json('receipt');
            $table->dateTime('accepted_at', 6);
            $table->unique(['account_id', 'id']);
            $table->unique(['account_id', 'id', 'credential_id'], 'api_request_credential_identity');
            $table->unique(['account_id', 'idempotency_hash'], 'api_idempotency_once');
            $table->foreign(['account_id', 'credential_id'], 'api_request_credential_fk')->references(['account_id', 'id'])->on('account_api_keys')->cascadeOnDelete();
        });
        Schema::create('api_mail_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->uuid('api_request_id');
            $table->unsignedSmallInteger('position');
            $table->string('email', 254)->collation('utf8mb4_bin');
            $table->string('name', 150);
            // Historical bound identity, intentionally retained after hard contact deletion.
            $table->json('contact_ids');
            $table->string('subject', 200);
            $table->mediumText('html');
            $table->mediumText('text');
            $table->unique(['account_id', 'api_request_id', 'id'], 'api_recipient_provenance');
            $table->unique(['api_request_id', 'position']);
            $table->unique(['api_request_id', 'email']);
            $table->foreign(['account_id', 'api_request_id'], 'api_recipient_request_fk')->references(['account_id', 'id'])->on('api_mail_requests')->cascadeOnDelete();
        });
        // Make only the explicit source alternative nullable; original composite FKs remain.
        DB::statement('ALTER TABLE deliveries MODIFY campaign_id BIGINT UNSIGNED NULL, MODIFY campaign_revision_id BIGINT UNSIGNED NULL, MODIFY campaign_recipient_id BIGINT UNSIGNED NULL');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->uuid('api_request_id')->nullable();
            $table->uuid('api_recipient_id')->nullable();
            $table->unique('api_recipient_id', 'delivery_api_recipient_once');
            $table->unique(['account_id', 'id', 'api_request_id'], 'delivery_api_identity');
            $table->foreign(['account_id', 'api_request_id', 'api_recipient_id'], 'delivery_api_source_fk')->references(['account_id', 'api_request_id', 'id'])->on('api_mail_recipients')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE deliveries ADD CONSTRAINT delivery_source_branch CHECK ((campaign_id IS NOT NULL AND campaign_revision_id IS NOT NULL AND campaign_recipient_id IS NOT NULL AND api_request_id IS NULL AND api_recipient_id IS NULL) OR (campaign_id IS NULL AND campaign_revision_id IS NULL AND campaign_recipient_id IS NULL AND api_request_id IS NOT NULL AND api_recipient_id IS NOT NULL))');
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('api_credential_id')->nullable();
            $table->uuid('api_request_id')->nullable();
            $table->foreign(['account_id', 'api_request_id', 'api_credential_id'], 'message_api_credential_fk')->references(['account_id', 'id', 'credential_id'])->on('api_mail_requests')->cascadeOnDelete();
            $table->foreign(['account_id', 'delivery_id', 'api_request_id'], 'message_api_request_fk')->references(['account_id', 'id', 'api_request_id'])->on('deliveries')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE mail_messages ADD CONSTRAINT message_api_origin CHECK ((origin = 'api' AND api_credential_id IS NOT NULL AND api_request_id IS NOT NULL AND delivery_id IS NOT NULL) OR (origin <> 'api' AND api_credential_id IS NULL AND api_request_id IS NULL))");
        // actor_id retains its SET NULL user FK. MySQL disallows checking that
        // column; the common transport and MailMessage guard enforce API actor=null.
    }

    public function down(): void
    {
        // A downgrade cannot represent accepted API work. Fail before destructive DDL.
        if (DB::table('api_mail_requests')->exists()) {
            throw new RuntimeException('Archive API work before downgrading the API schema.');
        }
        DB::statement('ALTER TABLE mail_messages DROP CHECK message_api_origin');
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropForeign('message_api_credential_fk');
            $table->dropForeign('message_api_request_fk');
            $table->dropColumn(['api_credential_id', 'api_request_id']);
        });
        DB::statement('ALTER TABLE deliveries DROP CHECK delivery_source_branch');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign('delivery_api_source_fk');
            $table->dropUnique('delivery_api_recipient_once');
            $table->dropUnique('delivery_api_identity');
            $table->dropColumn(['api_request_id', 'api_recipient_id']);
        });
        DB::statement('ALTER TABLE deliveries MODIFY campaign_id BIGINT UNSIGNED NOT NULL, MODIFY campaign_revision_id BIGINT UNSIGNED NOT NULL, MODIFY campaign_recipient_id BIGINT UNSIGNED NOT NULL');
        Schema::dropIfExists('api_mail_recipients');
        Schema::dropIfExists('api_mail_requests');
        Schema::dropIfExists('api_admission_windows');
        Schema::table('account_api_keys', fn (Blueprint $table) => $table->dropForeign('api_key_parent_fk'));
        Schema::dropIfExists('account_api_keys');
    }
};
