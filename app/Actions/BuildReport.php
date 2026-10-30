<?php

namespace App\Actions;

use App\Enums\TransactionTypeEnum;
use App\Models\Household;
use App\Models\Transaction;
use Carbon\CarbonInterface;

class BuildReport
{
    /** Spending of one calendar month, grouped by budget and by person. Amounts are encrypted, so this sums in PHP. */
    public function handle(Household $household, CarbonInterface $month): array
    {
        $rows = $household->transactions()
            ->whereIn('type', [TransactionTypeEnum::EXPENSE->value, TransactionTypeEnum::INCOME->value]) // moves are not spending
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->with(['budgetMonth.budget', 'user', 'corrects' => fn ($q) => $q->withTrashed()])
            ->get();

        $spent = $income = 0;
        $byBudget = $byPerson = [];

        foreach ($rows as $t) {
            [$s, $i] = $this->effect($t);
            $spent += $s;
            $income += $i;

            $budget = $t->budgetMonth->budget;
            $byBudget[$budget->id] ??= ['id' => $budget->id, 'name' => $budget->name, 'spent' => 0];
            $byBudget[$budget->id]['spent'] += $s;

            $byPerson[$t->user_id] ??= ['id' => $t->user_id, 'name' => $t->user->name, 'spent' => 0];
            $byPerson[$t->user_id]['spent'] += $s;
        }

        return [
            'has_data' => $rows->isNotEmpty(),
            'spent' => $spent,
            'income' => $income,
            'budgets' => $this->share($byBudget),
            'people' => $this->share($byPerson),
        ];
    }

    /** @return array{int, int} what this entry adds to [spent, income]; corrections subtract */
    private function effect(Transaction $t): array
    {
        $original = $t->corrects?->type;

        return match (true) {
            $t->type === TransactionTypeEnum::EXPENSE && $original === TransactionTypeEnum::INCOME => [0, -$t->amount],
            $t->type === TransactionTypeEnum::INCOME && $original === TransactionTypeEnum::EXPENSE => [-$t->amount, 0],
            $t->type === TransactionTypeEnum::EXPENSE => [$t->amount, 0],
            default => [0, $t->amount],
        };
    }

    /** Biggest first, with each one's share of what was spent. Refunds that net below zero are left out of the lists. */
    private function share(array $items): array
    {
        $items = collect($items)->filter(fn ($i) => $i['spent'] > 0)->sortByDesc('spent')->values();
        $total = $items->sum('spent');

        return $items->map(fn ($i) => [...$i, 'pct' => $total > 0 ? (int) round($i['spent'] / $total * 100) : 0])->all();
    }
}
