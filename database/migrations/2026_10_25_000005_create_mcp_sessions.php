<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->boolean('initialized')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_sessions');
    }
};
