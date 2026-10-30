<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', fn (Blueprint $t) => $t->index(['household_id', 'archived_at']));
        Schema::table('household_user', fn (Blueprint $t) => $t->index('user_id'));
        Schema::table('household_invites', fn (Blueprint $t) => $t->index(['household_id', 'expires_at']));
        Schema::table('transactions', fn (Blueprint $t) => $t->index('user_id'));
    }

    public function down(): void
    {
        Schema::table('budgets', fn (Blueprint $t) => $t->dropIndex(['household_id', 'archived_at']));
        Schema::table('household_user', fn (Blueprint $t) => $t->dropIndex(['user_id']));
        Schema::table('household_invites', fn (Blueprint $t) => $t->dropIndex(['household_id', 'expires_at']));
        Schema::table('transactions', fn (Blueprint $t) => $t->dropIndex(['user_id']));
    }
};
