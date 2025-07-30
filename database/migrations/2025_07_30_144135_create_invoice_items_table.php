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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('restrict');
            $table->string('product_name'); // Store name in case product is deleted
            $table->string('product_sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('gold_weight', 10, 3)->default(0);
            $table->decimal('stone_weight', 10, 3)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('gold_price_per_gram', 12, 2)->default(0);
            $table->decimal('manufacturing_fee', 12, 2)->default(0);
            $table->decimal('profit_amount', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->json('custom_attributes')->nullable();
            $table->timestamps();
            
            $table->index(['invoice_id']);
            $table->index(['product_id']);
            $table->index(['product_sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
