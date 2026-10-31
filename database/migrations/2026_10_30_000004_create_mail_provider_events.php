<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_provider_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('mail_message_id');
            $table->string('provider', 10);
            $table->char('event_key', 64)->collation('ascii_bin');
            $table->string('status', 30);
            $table->dateTime('occurred_at', 6);
            $table->timestamps();
            $table->unique(['account_id', 'provider', 'event_key']);
            $table->foreign(['account_id', 'mail_message_id'])->references(['account_id', 'id'])->on('mail_messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_provider_events');
    }
};
