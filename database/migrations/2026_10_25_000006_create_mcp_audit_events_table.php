<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // Audit retention is independent of authorization tombstone retention.
            $table->uuid('client_id')->nullable();
            $table->uuid('grant_id')->nullable();
            $table->string('operation', 64);
            $table->string('outcome', 16);
            $table->uuid('correlation_id');
            $table->timestamp('created_at');
            $table->index(['actor_id', 'created_at']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_events');
    }
};
