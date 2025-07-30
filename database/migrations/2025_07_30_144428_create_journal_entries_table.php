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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->text('description');
            $table->string('reference')->nullable(); // Reference to source document
            $table->string('reference_type')->nullable(); // 'invoice', 'payment', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('total_debit', 15, 2);
            $table->decimal('total_credit', 15, 2);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_pattern')->nullable(); // monthly, quarterly, yearly
            $table->date('next_recurring_date')->nullable();
            $table->boolean('is_system_generated')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            
            $table->index(['entry_number']);
            $table->index(['entry_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['status']);
            $table->index(['is_recurring']);
            $table->index(['is_system_generated']);
            $table->index(['created_by']);
            $table->index(['posted_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
