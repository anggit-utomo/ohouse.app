<?php

namespace App\Actions;

use App\Enums\TransactionTypeEnum;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\Period;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildReport
{
    /**
     * @return array{
     *      has_data: bool,
     *      spent: int,
     *      income: int,
     *      budgets: list<array{id: int, name: string, spent: int, pct: int}>,
     *      people: list<array{id: int, name: string, spent: int, pct: int}>
     * }
     */
    public function handle(Household $household, Period $period): array
    {
        $rows = $household->transactions()
            ->whereIn(
                'type',
                [TransactionTypeEnum::EXPENSE->value, TransactionTypeEnum::INCOME->value]
            ) // moves are not spending
            ->whereBetween('occurred_at', [$period->start->startOfDay(), $period->endOrNow()])
            ->with(['budgetMonth.budget', 'user', 'corrects' => fn (BelongsTo $q) => $q->withTrashed()])
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

    /**
     * @param  array<int, array{id: int, name: string, spent: int}>  $items
     * @return list<array{id: int, name: string, spent: int, pct: int}>
     */
    private function share(array $items): array
    {
        $items = collect($items)->filter(fn ($i) => $i['spent'] > 0)->sortByDesc('spent')->values();
        $total = $items->sum('spent');

        return $items->map(fn ($i) => [...$i, 'pct' => $total > 0 ? (int) round($i['spent'] / $total * 100) : 0])->all();
    }
}
