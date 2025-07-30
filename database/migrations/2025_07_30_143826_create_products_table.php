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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('restrict');
            $table->enum('type', ['raw_gold', 'finished_jewelry', 'coins', 'stones', 'other']);
            $table->text('description')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('gold_weight', 10, 3)->default(0); // Gold weight in grams
            $table->decimal('stone_weight', 10, 3)->default(0); // Stone weight in grams
            $table->decimal('total_weight', 10, 3)->default(0); // Total weight in grams
            $table->decimal('manufacturing_cost', 12, 2)->default(0);
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->decimal('minimum_stock', 10, 3)->default(0);
            $table->decimal('maximum_stock', 10, 3)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->string('unit_of_measure')->default('piece'); // piece, gram, carat, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(true);
            $table->boolean('has_bom')->default(false); // Has Bill of Materials
            $table->json('images')->nullable();
            $table->json('specifications')->nullable();
            $table->json('tags')->nullable();
            $table->string('location')->nullable(); // Storage location
            $table->timestamps();
            
            $table->index(['sku']);
            $table->index(['barcode']);
            $table->index(['category_id']);
            $table->index(['type']);
            $table->index(['is_active']);
            $table->index(['track_stock']);
            $table->index(['current_stock']);
            $table->index(['minimum_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
