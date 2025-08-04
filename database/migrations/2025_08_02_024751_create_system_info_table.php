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
        Schema::create('system_info', function (Blueprint $table) {
            $table->id();
            $table->string('system_name', 100)->default('FactuGriego');
            $table->string('version', 20)->default('1.0.0');
            $table->date('release_date')->nullable();
            $table->string('owner', 200)->default('Construcciones Griegas B&B S.A.');
            $table->string('developer', 200)->nullable();
            $table->text('technologies')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_info');
    }
};
