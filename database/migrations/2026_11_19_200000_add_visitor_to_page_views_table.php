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
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('visitor', 64)->nullable()->after('session');
            $table->index(['created_at', 'visitor'], 'page_views_created_at_visitor_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex('page_views_created_at_visitor_index');
            $table->dropColumn('visitor');
        });
    }
};
