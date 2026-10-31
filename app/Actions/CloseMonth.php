<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\Periods;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseMonth
{
    /** Unclosed rows of the current period, in budget order. */
    public function open(
        Household $household,
        bool $lock = false,
        bool $withArchived = false
    ): Collection {
        return BudgetMonth::forHousehold($household)
            ->whereDate('period_start', Periods::current($household)->key())
            ->whereNull('closed_at')
            ->when(! $withArchived, fn ($q) => $q->whereHas('budget', fn ($b) => $b->active()))
            ->with(['budget', 'transactions'])
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get()
            ->sortBy(fn (BudgetMonth $m) => [$m->budget->sort, $m->budget_id])
            ->values();
    }

    /**
     * What a budget has left if the new period starts on $start:
     * entries dated on or after it move on.
     */
    private function leftoverBefore(BudgetMonth $m, CarbonInterface $start): int
    {
        $day = $start->copy()->startOfDay();

        return $m->allocated + $m->carried_in + $m->transactions
            ->filter(fn (Transaction $t) => $t->occurred_at < $day)
            ->sum(fn (Transaction $t) => $t->signedAmount());
    }

    /**
     * For the screen: repeating budgets with their leftovers,
     * and the one-off budgets that end.
     */
    public function preview(Household $household, CarbonInterface $start): array
    {
        $day = $start->copy()->startOfDay();
        $open = $this->open($household);
        $hasLater = fn (BudgetMonth $m) => $m->transactions->contains(fn (Transaction $t) => $t->occurred_at >= $day);

        return [
            'rows' => $open->filter(
                fn (BudgetMonth $m) => $m->budget->repeat_monthly
            )->map(fn (BudgetMonth $m) => [
                'budget_id' => $m->budget_id,
                'name' => $m->budget->name,
                'leftover' => $this->leftoverBefore($m, $day),
                'next_allocated' => $m->budget->default_amount,
            ])->values()->all(),
            'ending' => $open->reject(
                fn (BudgetMonth $m) => $m->budget->repeat_monthly || $hasLater($m)
            )
                ->map(fn (BudgetMonth $m) => $m->budget->name)->values()->all(),
        ];
    }

    /**
     * Starts the next period on $start.
     *
     * @param  array<int, string>  $decisions  budget_id => 'carry' | 'reset', for repeating budgets only
     */
    public function handle(Household $household, CarbonInterface $start, array $decisions): void
    {
        $day = $start->copy()->startOfDay();

        DB::transaction(function () use ($household, $day, $decisions) {
            foreach ($this->open($household, lock: true, withArchived: true) as $month) {
                $budget = $month->budget;
                $active = $budget->archived_at === null;
                $repeats = $active && $budget->repeat_monthly;
                $moved = $month->transactions->filter(fn (Transaction $t) => $t->occurred_at >= $day);

                $decision = null;

                if ($repeats) {
                    $decision = $decisions[$month->budget_id]
                        ?? throw new InvalidArgumentException(
                            "Missing decision for budget {$month->budget_id}."
                        );

                    if (! in_array($decision, ['carry', 'reset'], true)) {
                        throw new InvalidArgumentException("Invalid decision '{$decision}'.");
                    }
                }

                $carry = $decision === 'carry' ? $this->leftoverBefore($month, $day) : 0; // can be negative

                if ($repeats || $moved->isNotEmpty()) {
                    $next = BudgetMonth::create([
                        'budget_id' => $budget->id,
                        'period_start' => $day->toDateString(),
                        'allocated' => $repeats ? $budget->default_amount : 0,
                        'carried_in' => $carry,
                    ]);

                    // entries dated on or after payday belong to the new period
                    Transaction::whereKey($moved->modelKeys())
                        ->update(['budget_month_id' => $next->id]);
                } elseif ($active) {
                    $budget->update(['archived_at' => now()]); // a one-off budget ends with its period
                }

                $month->update([
                    'carry_decision' => $decision,
                    'closed_at' => now(),
                    'period_end' => $day->copy()->subDay()->toDateString(),
                ]);
            }

            $household->update(['period_start' => $day->toDateString()]);
        });
    }
}
