<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_list_memberships', function (Blueprint $table) {
            $table->index(['contact_list_id', 'contact_id'], 'contact_list_memberships_list_contact_index');
        });
    }

    public function down(): void
    {
        Schema::table('contact_list_memberships', function (Blueprint $table) {
            $table->dropIndex('contact_list_memberships_list_contact_index');
        });
    }
};
