<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->char('state_hash', 64)->unique();
            $table->char('binding_hash', 64);
            $table->char('nonce_hash', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('intent', 16);
            $table->string('return_to', 512);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
        });
        Schema::create('google_login_codes', function (Blueprint $table) {
            $table->char('code_hash', 64)->primary();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_login_codes');
        Schema::dropIfExists('google_login_attempts');
    }
};
