<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_months', function (Blueprint $table) {
            $table->index(['period_start', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_months', function (Blueprint $table) {
            $table->dropIndex(['period_start', 'closed_at']);
        });
    }
};
