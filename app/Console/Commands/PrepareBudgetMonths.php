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
        $created = 0;

        Budget::active()->where('repeat_monthly', true)->each(function (Budget $budget) use ($period, &$created) {
            $exists = BudgetMonth::where('budget_id', $budget->id)
                ->whereDate('period_start', $period)
                ->exists();

            if ($exists) {
                return;
            }

            BudgetMonth::create([
                'budget_id' => $budget->id,
                'period_start' => $period,
                'allocated' => $budget->default_amount,
                'carried_in' => 0,
            ]);

            $created++;
        });

        $this->info("Created {$created} budget month(s) for {$period}.");

        return self::SUCCESS;
    }
}
