<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_clients', function (Blueprint $table): void {
            $table->string('id', 128)->primary();
            $table->string('name', 150);
            $table->json('redirect_uris');
            $table->boolean('native_port_relaxation')->default(false);
            $table->boolean('dynamic')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('mcp_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_id', 128);
            $table->foreign('client_id')->references('id')->on('mcp_clients')->restrictOnDelete();
            $table->string('issuer', 2048);
            $table->string('resource', 2048);
            $table->json('scopes');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['id', 'account_id', 'client_id'], 'mcp_grant_authority');
            $table->index(['account_id', 'user_id']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->string('purpose', 20);
            $table->foreignId('account_id');
            $table->string('client_id', 128);
            $table->uuid('grant_id');
            $table->foreign(['grant_id', 'account_id', 'client_id'], 'mcp_token_grant')->references(['id', 'account_id', 'client_id'])->on('mcp_grants')->restrictOnDelete();
            $table->string('issuer', 2048);
            $table->string('resource', 2048);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('mcp_grants');
        Schema::dropIfExists('mcp_clients');
    }
};
