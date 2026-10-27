<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Expand transactional source provenance while retaining the existing REST schema. */
return new class extends Migration
{
    public function up(): void
    {
        // Preregistered public OAuth client identifiers allow 128 characters;
        // audit correlation must not silently assume dynamically generated UUIDs.
        DB::statement('ALTER TABLE mcp_audit_events MODIFY client_id VARCHAR(128) NULL');
        // The SQL default keeps old REST writers compatible with the expansion.
        Schema::table('api_mail_requests', function (Blueprint $table): void {
            $table->string('origin', 16)->default('api');
            $table->uuid('mcp_grant_id')->nullable();
            $table->string('mcp_client_id', 128)->nullable();
            $table->unique(['account_id', 'id', 'mcp_grant_id', 'mcp_client_id'], 'request_mcp_origin_identity');
            $table->foreign(['mcp_grant_id', 'account_id', 'mcp_client_id'], 'request_mcp_grant_fk')
                ->references(['id', 'account_id', 'client_id'])->on('mcp_grants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE api_mail_requests MODIFY credential_id BIGINT UNSIGNED NULL, ADD CONSTRAINT request_origin_branch CHECK ((origin = 'api' AND credential_id IS NOT NULL AND mcp_grant_id IS NULL AND mcp_client_id IS NULL) OR (origin = 'mcp' AND credential_id IS NULL AND mcp_grant_id IS NOT NULL AND mcp_client_id IS NOT NULL))");

        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->uuid('mcp_grant_id')->nullable();
            $table->string('mcp_client_id', 128)->nullable();
            $table->foreign(['account_id', 'api_request_id', 'mcp_grant_id', 'mcp_client_id'], 'message_mcp_origin_fk')
                ->references(['account_id', 'id', 'mcp_grant_id', 'mcp_client_id'])->on('api_mail_requests')->cascadeOnDelete();
        });
        // Replace the check atomically; retain both established API composite FKs.
        DB::statement("ALTER TABLE mail_messages DROP CHECK message_api_origin, ADD CONSTRAINT message_api_origin CHECK ((origin = 'api' AND api_credential_id IS NOT NULL AND api_request_id IS NOT NULL AND delivery_id IS NOT NULL AND mcp_grant_id IS NULL AND mcp_client_id IS NULL) OR (origin = 'mcp' AND api_credential_id IS NULL AND api_request_id IS NOT NULL AND delivery_id IS NOT NULL AND mcp_grant_id IS NOT NULL AND mcp_client_id IS NOT NULL) OR (origin NOT IN ('api', 'mcp') AND api_credential_id IS NULL AND api_request_id IS NULL AND mcp_grant_id IS NULL AND mcp_client_id IS NULL))");
        // User attribution derives from the retained immutable grant. Its user FK
        // and MailMessage.actor_id may become NULL after deletion, without deleting
        // requests or substituting another user. No token is a work-source FK.
    }

    public function down(): void
    {
        // The prior schema cannot represent MCP work. Refuse before any DDL.
        if (DB::table('api_mail_requests')->where('origin', 'mcp')->exists()) {
            throw new RuntimeException('Archive MCP work before downgrading the shared source schema.');
        }
        DB::statement("ALTER TABLE mail_messages DROP CHECK message_api_origin, ADD CONSTRAINT message_api_origin CHECK ((origin = 'api' AND api_credential_id IS NOT NULL AND api_request_id IS NOT NULL AND delivery_id IS NOT NULL) OR (origin <> 'api' AND api_credential_id IS NULL AND api_request_id IS NULL))");
        Schema::table('mail_messages', function (Blueprint $table): void {
            $table->dropForeign('message_mcp_origin_fk');
            $table->dropColumn(['mcp_grant_id', 'mcp_client_id']);
        });
        DB::statement('ALTER TABLE api_mail_requests DROP CHECK request_origin_branch, MODIFY credential_id BIGINT UNSIGNED NOT NULL');
        Schema::table('api_mail_requests', function (Blueprint $table): void {
            $table->dropForeign('request_mcp_grant_fk');
            $table->dropUnique('request_mcp_origin_identity');
            $table->dropColumn(['origin', 'mcp_grant_id', 'mcp_client_id']);
        });
        // Keep the compatible audit width: a rollback must not truncate retained
        // client attribution. Earlier application writers can use the wider field.
    }
};
