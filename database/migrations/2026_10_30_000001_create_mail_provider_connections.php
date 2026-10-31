<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_provider_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->boolean('enabled')->default(false);
            $table->string('smtp_security', 20)->default('starttls');
            foreach (['api_key', 'smtp_username', 'smtp_password', 'webhook_secret', 'webhook_public_key'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
            $table->unique(['account_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_provider_connections');
    }
};
