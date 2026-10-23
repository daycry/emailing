<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_email', 254)->collation('utf8mb4_bin');
            $table->string('source', 30);
            $table->timestamps();
            $table->unique(['account_id', 'normalized_email']);
        });
        Schema::create('unsubscribe_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('normalized_email', 254)->collation('utf8mb4_bin');
            $table->char('token_hash', 64)->collation('ascii_bin')->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'id']);
        });
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->timestamp('transport_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', fn (Blueprint $table) => $table->dropColumn('transport_started_at'));
        Schema::dropIfExists('unsubscribe_tokens');
        Schema::dropIfExists('suppressions');
    }
};
