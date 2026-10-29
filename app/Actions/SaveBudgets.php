<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SaveBudgets
{
    /**
     * Saves the whole Set budgets form for one month.
     * Rows missing from the list are archived (history is kept), not deleted.
     *
     * @param  list<array{id: ?int, name: string, amount: int}>  $rows
     */
    public function handle(Household $household, CarbonInterface $period, array $rows, bool $repeat): void
    {
        $period = $period->copy()->startOfMonth();

        DB::transaction(function () use ($household, $period, $rows, $repeat) {
            $kept = [];

            foreach (array_values($rows) as $i => $row) {
                $budget = $row['id']
                    ? $household->budgets()->findOrFail($row['id'])
                    : $household->budgets()->make();

                // default_amount is what future months start from; the month row holds this month's amount.
                $budget->fill([
                    'name' => $row['name'],
                    'default_amount' => $row['amount'],
                    'repeat_monthly' => $repeat,
                    'sort' => $i,
                ])->save();

                $month = BudgetMonth::where('budget_id', $budget->id)
                    ->whereDate('period_start', $period->toDateString())
                    ->first()
                    ?? new BudgetMonth(['budget_id' => $budget->id, 'period_start' => $period->toDateString(), 'carried_in' => 0]);

                $month->allocated = $row['amount'];
                $month->save();

                $kept[] = $budget->id;
            }

            $household->budgets()->active()->whereNotIn('id', $kept)->update(['archived_at' => now()]);
        });
    }
}
