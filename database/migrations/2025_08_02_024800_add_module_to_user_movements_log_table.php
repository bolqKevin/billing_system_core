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
        Schema::table('user_movements_log', function (Blueprint $table) {
            $table->string('module', 50)->nullable()->after('affected_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_movements_log', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
