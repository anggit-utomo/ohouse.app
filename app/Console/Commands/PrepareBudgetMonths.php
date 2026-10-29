<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\BudgetMonth;
use Illuminate\Console\Command;

class PrepareBudgetMonths extends Command
{
    protected $signature = 'budgets:prepare-month';

    protected $description = 'Create this month\'s budget rows for budgets set to repeat monthly';

    public function handle(): int
    {
        $period = now()->startOfMonth()->toDateString();

        Budget::active()->where('repeat_monthly', true)->each(function (Budget $budget) use ($period) {
            BudgetMonth::firstOrCreate(
                ['budget_id' => $budget->id, 'period_start' => $period],
                ['allocated' => $budget->default_amount, 'carried_in' => 0],
            );
        });

        return self::SUCCESS;
    }
}
