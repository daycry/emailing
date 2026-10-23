<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->uuid('lease_token')->nullable();
            $table->dateTime('lease_expires_at', 6)->nullable();
            $table->dateTime('available_at', 6)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('mail_message_id')->nullable();
            $table->string('diagnostic', 50)->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->index(['status', 'lease_expires_at', 'id'], 'delivery_recovery');
        });
        Schema::create('delivery_quota_windows', function (Blueprint $table) {
            $table->string('id', 160)->primary();
            $table->unsignedInteger('used')->default(0);
            $table->dateTime('ends_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_quota_windows');
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('delivery_recovery');
            $table->dropColumn(['lease_token', 'lease_expires_at', 'available_at', 'attempts', 'mail_message_id', 'diagnostic', 'started_at', 'completed_at']);
        });
    }
};
