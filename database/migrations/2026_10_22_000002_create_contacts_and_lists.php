<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254)->collation('utf8mb4_bin');
            $table->string('name', 150)->default('');
            $table->string('consent_status', 20)->default('unknown');
            $table->string('consent_source', 255)->nullable();
            $table->dateTime('consented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('active_email', 254)->nullable()->collation('utf8mb4_bin')->storedAs('case when deleted_at is null then email else null end');
            $table->unique(['account_id', 'id']);
            $table->unique(['account_id', 'active_email']);
        });
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->default('');
            $table->timestamps();
            $table->unique(['account_id', 'id']);
        });
        Schema::create('contact_list_memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('contact_list_id');
            $table->unsignedBigInteger('contact_id');
            $table->primary(['account_id', 'contact_list_id', 'contact_id'], 'contact_list_memberships_primary');
            $table->foreign(['account_id', 'contact_list_id'])->references(['account_id', 'id'])->on('contact_lists')->cascadeOnDelete();
            $table->foreign(['account_id', 'contact_id'])->references(['account_id', 'id'])->on('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_list_memberships');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('contacts');
    }
};
