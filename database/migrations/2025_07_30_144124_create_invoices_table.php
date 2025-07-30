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
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->enum('type', ['sale', 'purchase', 'trade']);
            $table->enum('status', ['draft', 'pending', 'paid', 'partial', 'cancelled', 'overdue']);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->decimal('total_gold_weight', 10, 3)->default(0);
            $table->decimal('gold_price_per_gram', 12, 2)->default(0);
            $table->decimal('manufacturing_fee', 12, 2)->default(0);
            $table->decimal('profit_margin_percentage', 5, 2)->default(0);
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_pattern')->nullable(); // monthly, quarterly, yearly
            $table->date('next_recurring_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index(['invoice_number']);
            $table->index(['customer_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['invoice_date']);
            $table->index(['due_date']);
            $table->index(['is_recurring']);
            $table->index(['created_by']);
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
