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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('payment_number')->unique();
            $table->enum('payment_method', ['cash', 'card', 'cheque', 'credit', 'bank_transfer']);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled']);
            $table->text('notes')->nullable();
            $table->string('reference_number')->nullable(); // Cheque number, transaction ID, etc.
            $table->string('bank_name')->nullable();
            $table->date('cheque_date')->nullable();
            $table->json('payment_details')->nullable(); // Additional payment-specific data
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['invoice_id']);
            $table->index(['customer_id']);
            $table->index(['payment_number']);
            $table->index(['payment_method']);
            $table->index(['status']);
            $table->index(['payment_date']);
            $table->index(['processed_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
