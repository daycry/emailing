<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_clients', function (Blueprint $table): void {
            $table->string('issuer', 2048)->nullable();
        });
        Schema::create('mcp_authorization_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('browser_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('client_id', 128);
            $table->foreign('client_id')->references('id')->on('mcp_clients')->restrictOnDelete();
            $table->string('redirect_uri', 2048);
            $table->string('resource', 2048);
            $table->json('scopes');
            $table->text('state')->nullable();
            $table->string('code_challenge', 43);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'client_id', 'consumed_at'], 'mcp_pending_owner');
        });
        Schema::create('mcp_authorization_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->uuid('grant_id');
            $table->foreign('grant_id')->references('id')->on('mcp_grants')->restrictOnDelete();
            $table->string('client_id', 128);
            $table->string('redirect_uri', 2048);
            $table->string('resource', 2048);
            $table->string('code_challenge', 43);
            $table->unsignedTinyInteger('verifier_attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('authorization_code_id')->nullable()->constrained('mcp_authorization_codes')->restrictOnDelete();
        });
        Schema::create('mcp_admission_windows', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('expires_at')->index();
        });
        Schema::create('mcp_issuer_mutexes', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_issuer_mutexes');
        Schema::dropIfExists('mcp_admission_windows');
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('authorization_code_id');
        });
        Schema::dropIfExists('mcp_authorization_codes');
        Schema::dropIfExists('mcp_authorization_requests');
        Schema::table('mcp_clients', function (Blueprint $table): void {
            $table->dropColumn('issuer');
        });
    }
};
