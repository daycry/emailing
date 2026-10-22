<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 20)->default('log');
            $table->string('smtp_host', 253)->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_security', 20)->default('starttls');
            $table->text('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->timestamps();
        });
        Schema::create('mail_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254)->collation('utf8mb4_bin');
            $table->string('name', 150)->default('');
            $table->string('reply_to', 254)->nullable();
            $table->boolean('lab_authorized')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->string('active_email', 254)->nullable()->collation('utf8mb4_bin')->storedAs('case when deleted_at is null then email else null end');
            $table->unique(['account_id', 'active_email']);
            $table->unique(['account_id', 'id']);
        });
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('mail_sender_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20);
            $table->string('status', 30);
            $table->string('diagnostic', 50)->nullable();
            $table->string('sender_email', 254);
            $table->string('recipient', 254);
            $table->string('subject', 200);
            $table->string('artifact_path')->nullable();
            $table->timestamps();
            $table->foreign(['account_id', 'mail_sender_id'])->references(['account_id', 'id'])->on('mail_senders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_senders');
        Schema::dropIfExists('mail_settings');
    }
};
