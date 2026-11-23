<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'page_views_created_at_visitor_index';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('page_views', 'visitor')) {
            Schema::table('page_views', function (Blueprint $table) {
                $table->string('visitor', 64)->nullable()->after('session');
            });
        }

        if (! Schema::hasIndex('page_views', self::INDEX)) {
            Schema::table('page_views', function (Blueprint $table) {
                $table->index(['created_at', 'visitor'], self::INDEX);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
            $table->dropColumn('visitor');
        });
    }
};
