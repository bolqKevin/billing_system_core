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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('payment_method', ['Cash', 'Transfer', 'Card', 'Check', 'Other']);
            $table->enum('sale_condition', ['Cash', 'Credit']);
            $table->integer('credit_days')->default(0);
            $table->text('observations')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_tax', 12, 2);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->enum('status', ['Draft', 'Issued', 'Cancelled'])->default('Draft');
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('creation_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
