<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Support\Periods;
use Illuminate\Support\Facades\DB;

class SaveBudgets
{
    /**
     * Saves the Set budgets form for the current period.
     * Rows missing from the list are archived (history is kept), not deleted.
     *
     * @param  list<array{id: ?int, name: string, amount: int, repeat: bool}>  $rows
     */
    public function handle(Household $household, array $rows): void
    {
        $period = Periods::current($household);

        DB::transaction(function () use ($household, $rows, $period) {
            $kept = [];

            foreach ($rows as $i => $row) {
                $budget = $row['id']
                    ? $household->budgets()->findOrFail($row['id'])
                    : $household->budgets()->make();

                // default_amount is what the next period starts from; the month row holds this period's amount
                $budget->fill([
                    'name' => $row['name'],
                    'default_amount' => $row['amount'],
                    'repeat_monthly' => (bool) $row['repeat'],
                    'sort' => $i,
                ])->save();

                $month = BudgetMonth::where('budget_id', $budget->id)
                    ->whereDate('period_start', $period->key())
                    ->first() ?? new BudgetMonth([
                        'budget_id' => $budget->id,
                        'period_start' => $period->key(),
                        'carried_in' => 0,
                    ]);

                $month->allocated = $row['amount'];
                $month->save();

                $kept[] = $budget->id;
            }

            $household->budgets()->active()->whereNotIn('id', $kept)->update(['archived_at' => now()]);

            if (! $household->period_start) {
                $household->update(['period_start' => $period->key()]);
            }
        });
    }
}
