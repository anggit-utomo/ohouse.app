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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_month_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(); // paid / received by
            $table->string('type'); // expense | income | transfer_in | transfer_out
            $table->text('amount'); // encrypted integer
            $table->text('note')->nullable(); // encrypted
            $table->dateTime('occurred_at');
            $table->uuid('transfer_id')->nullable()->index();
            $table->foreignId('corrects_transaction_id')->nullable()->constrained('transactions');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['household_id', 'occurred_at']);
            $table->index(['budget_month_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
