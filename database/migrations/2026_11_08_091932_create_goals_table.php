<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->text('name'); // encrypted
            $table->text('target_amount'); // encrypted integer
            $table->date('target_date')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
