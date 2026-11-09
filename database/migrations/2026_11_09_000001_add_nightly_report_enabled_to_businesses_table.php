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
        if (Schema::hasTable('businesses') && ! Schema::hasColumn('businesses', 'nightly_report_enabled')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->boolean('nightly_report_enabled')->default(true)->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('businesses') && Schema::hasColumn('businesses', 'nightly_report_enabled')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn('nightly_report_enabled');
            });
        }
    }
};

