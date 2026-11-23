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
        Schema::create('analytics_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('group_name', 100)->nullable();
            $table->string('label', 150);
            $table->string('href', 1024)->nullable();
            $table->string('target_host')->nullable();
            $table->string('uri');
            $table->string('visitor', 64)->nullable();
            $table->string('session', 64)->nullable();
            $table->string('device', 20)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index(['group_name', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_clicks');
    }
};
