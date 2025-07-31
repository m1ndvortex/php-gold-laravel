<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('cheque_due_date')->nullable()->after('cheque_date');
            $table->string('cheque_number')->nullable()->after('cheque_due_date');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cheque_due_date', 'cheque_number']);
        });
    }
};