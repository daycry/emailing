<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('destination_list_id')->nullable();
            $table->string('source_path');
            $table->unsignedInteger('total');
            $table->unsignedInteger('imported');
            $table->unsignedInteger('invalid');
            $table->unsignedInteger('duplicates');
            $table->timestamps();
            $table->unique(['account_id', 'id']);
        });
        Schema::create('contact_import_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('contact_import_id');
            $table->unsignedInteger('row_number');
            $table->string('email', 254)->collation('utf8mb4_bin');
            $table->string('status', 20);
            $table->string('message', 1000);
            $table->unique(['account_id', 'contact_import_id', 'row_number'], 'import_rows_number_unique');
            $table->foreign(['account_id', 'contact_import_id'])->references(['account_id', 'id'])->on('contact_imports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_import_rows');
        Schema::dropIfExists('contact_imports');
    }
};
