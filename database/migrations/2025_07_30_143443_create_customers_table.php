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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->onDelete('set null');
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('national_id')->nullable();
            $table->enum('customer_type', ['individual', 'business'])->default('individual');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('contact_preferences')->nullable();
            $table->timestamps();
            
            $table->index(['name']);
            $table->index(['phone']);
            $table->index(['email']);
            $table->index(['tax_id']);
            $table->index(['customer_group_id']);
            $table->index(['is_active']);
            $table->index(['last_transaction_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
