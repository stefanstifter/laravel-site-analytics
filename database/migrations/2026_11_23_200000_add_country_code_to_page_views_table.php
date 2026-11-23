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
        if (Schema::hasColumn('page_views', 'country_code')) {
            return;
        }

        Schema::table('page_views', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
