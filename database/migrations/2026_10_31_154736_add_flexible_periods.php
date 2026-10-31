<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_months', fn (Blueprint $t) => $t->date('period_end')->nullable());
        Schema::table('households', fn (Blueprint $t) => $t->date('period_start')->nullable());
    }

    public function down(): void
    {
        Schema::table('budget_months', fn (Blueprint $t) => $t->dropColumn('period_end'));
        Schema::table('households', fn (Blueprint $t) => $t->dropColumn('period_start'));
    }
};
