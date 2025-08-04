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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 200);
            $table->string('business_name', 200);
            $table->string('legal_id', 20)->unique();
            $table->text('address');
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->integer('invoice_current_consecutive')->default(1);
            $table->string('invoice_prefix', 10)->default('INV-');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
