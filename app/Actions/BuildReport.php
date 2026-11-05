<?php

namespace App\Actions;

use App\Enums\TransactionTypeEnum;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\Period;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BuildReport
{
    /**
     * Spending of one period, grouped by budget, person and day. Amounts are encrypted, so this sums in PHP.
     *
     * @return array<string, mixed>
     */
    public function handle(Household $household, Period $period): array
    {
        $rows = $household->transactions()
            ->whereIn('type', [TransactionTypeEnum::EXPENSE->value, TransactionTypeEnum::INCOME->value]) // moves are not spending
            ->whereBetween('occurred_at', [$period->start->startOfDay(), $period->endOrNow()])
            ->with(['budgetMonth.budget', 'user', 'corrects' => fn (BelongsTo $q) => $q->withTrashed()])
            ->get();

        $spent = $income = 0;
        $byBudget = $byPerson = $byDay = [];
        $expenses = collect();

        foreach ($rows as $t) {
            [$s, $i] = $this->effect($t);
            $spent += $s;
            $income += $i;

            $budget = $t->budgetMonth->budget;
            $byBudget[$budget->id] ??= ['id' => $budget->id, 'name' => $budget->name, 'spent' => 0];
            $byBudget[$budget->id]['spent'] += $s;

            $byPerson[$t->user_id] ??= ['id' => $t->user_id, 'name' => $t->user->name, 'initial' => $this->initial($t->user->name), 'spent' => 0];
            $byPerson[$t->user_id]['spent'] += $s;

            $day = $t->occurred_at->toDateString();
            $byDay[$day] = ($byDay[$day] ?? 0) + $s;

            // real expenses only: not income, moves, or corrections
            if ($s > 0 && $t->type === TransactionTypeEnum::EXPENSE) {
                $expenses->push($t);
            }
        }

        $daily = $this->daily($period, $byDay);
        $busiest = collect($daily)->sortByDesc('amount')->first();

        return [
            'has_data' => $rows->isNotEmpty(),
            'spent' => $spent,
            'income' => $income,
            'daily_spending' => $daily,
            'busiest_day' => $busiest && $busiest['amount'] > 0 ? $busiest['label'] : null,
            'budgets' => $this->share($byBudget),
            'biggest_expenses' => $expenses->sortByDesc('amount')->take(3)->map(fn (Transaction $t) => [
                'id' => $t->id,
                'name' => $t->note ?: $t->budgetMonth->budget->name,
                'category' => $t->budgetMonth->budget->name,
                'date' => $t->occurred_at->format('j M'),
                'amount' => $t->amount,
                'initial' => $this->initial($t->user->name),
            ])->values()->all(),
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

    /** One entry per day of the period (up to today for the open one), at most the last 62 days. */
    private function daily(Period $period, array $byDay): array
    {
        $days = [];
        $last = $period->endOrNow()->startOfDay();

        for ($d = $period->start->startOfDay(); $d->lte($last); $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[] = ['date' => $key, 'label' => $d->format('j M'), 'amount' => max(0, $byDay[$key] ?? 0)];
        }

        return array_slice($days, -62);
    }

    private function initial(string $name): string
    {
        return Str::upper(Str::substr($name, 0, 1));
    }

    /** Biggest first, with each one's share of what was spent. Refunds that net below zero are left out of the lists. */
    private function share(array $items): array
    {
        $items = collect($items)->filter(fn ($i) => $i['spent'] > 0)->sortByDesc('spent')->values();
        $total = $items->sum('spent');

        return $items->map(fn ($i) => [...$i, 'pct' => $total > 0 ? (int) round($i['spent'] / $total * 100) : 0])->all();
    }
}
