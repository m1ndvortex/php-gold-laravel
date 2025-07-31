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
        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('type'); // 'birthday', 'occasion', 'overdue_payment', 'credit_limit_exceeded'
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->text('message');
            $table->text('message_en')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->json('channels')->nullable(); // ['email', 'sms', 'whatsapp', 'system']
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed', 'cancelled'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'type']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
    }
};
