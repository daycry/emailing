<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Historical users chose a local password. Google-only creation explicitly sets false.
            $table->boolean('local_password_enabled')->default(true);
        });
        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->binary('provider', length: 32);
            $table->binary('issuer', length: 128);
            $table->binary('subject', length: 255);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'issuer', 'subject'], 'external_identity_exact');
        });
        Schema::create('identity_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('issuer', 128);
            $table->string('action', 32);
            $table->string('outcome', 16);
            $table->uuid('correlation_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_audit_events');
        Schema::dropIfExists('external_identities');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('local_password_enabled'));
    }
};
