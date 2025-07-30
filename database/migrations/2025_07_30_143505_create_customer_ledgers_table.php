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
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('transaction_type'); // 'debit', 'credit'
            $table->decimal('amount', 15, 2);
            $table->decimal('gold_amount', 10, 3)->default(0); // Gold weight in grams
            $table->string('currency', 3)->default('IRR');
            $table->text('description');
            $table->string('reference_type')->nullable(); // 'invoice', 'payment', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 15, 2);
            $table->decimal('gold_balance_after', 10, 3)->default(0);
            $table->timestamp('transaction_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'transaction_date']);
            $table->index(['transaction_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }
};
