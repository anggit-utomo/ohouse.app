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
        Schema::create('budget_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->text('allocated'); // encrypted integer
            $table->text('carried_in'); // encrypted signed integer
            $table->string('carry_decision')->nullable(); // carry | reset
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_months');
    }
};
