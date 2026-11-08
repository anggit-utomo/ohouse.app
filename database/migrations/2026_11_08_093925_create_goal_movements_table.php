<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(); // recorded by
            $table->foreignId('budget_month_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // contribution | withdrawal
            $table->text('amount'); // encrypted integer
            $table->text('note')->nullable(); // encrypted
            $table->dateTime('occurred_at');
            $table->date('period_start');
            $table->timestamps();
            $table->index(['goal_id', 'occurred_at']);
            $table->index(['household_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_movements');
    }
};
