<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'current_session_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('current_session_id', 100)->nullable()->after('remember_token');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_session_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('current_session_id');
            });
        }
    }
};

