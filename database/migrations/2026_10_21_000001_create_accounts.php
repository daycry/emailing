<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->foreignId('owner_id')->constrained('users'); $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id(); $table->foreignId('account_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); $table->json('scopes')->nullable(); $table->timestamps(); $table->unique(['account_id','user_id']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('account_id')->constrained()->cascadeOnDelete(); $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); $table->json('metadata')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('audit_events'); Schema::dropIfExists('memberships'); Schema::dropIfExists('accounts'); }
};
