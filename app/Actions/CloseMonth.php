<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseMonth
{
    /** Budget months of a household for one period, with transactions loaded for balance(). */
    public function months(Household $household, CarbonInterface $period, bool $lock = false): Collection
    {
        return BudgetMonth::query()
            ->whereDate('period_start', $period->copy()->startOfMonth()->toDateString())
            ->whereHas('budget', fn($q) => $q->where('household_id', $household->id))
            ->with(['budget', 'transactions'])
            ->when($lock, fn($q) => $q->lockForUpdate())
            ->get();
    }

    /** Leftover per budget, for the Close screen. Negative means over budget. */
    public function preview(Household $household, CarbonInterface $period): Collection
    {
        return $this->months($household, $period)->map(fn(BudgetMonth $m) => [
            'budget_id' => $m->budget_id,
            'name' => $m->budget->name,
            'leftover' => $m->balance(),
        ]);
    }

    /**
     * @param array<int, string> $decisions budget_id => 'carry' | 'reset'
     */
    public function handle(Household $household, CarbonInterface $period, array $decisions): void
    {
        $period = $period->copy()->startOfMonth();
        $next = $period->copy()->addMonthNoOverflow();

        DB::transaction(function () use ($household, $period, $next, $decisions) {
            foreach ($this->months($household, $period, lock: true) as $month) {
                if ($month->isClosed()) {
                    continue; // safe to run twice
                }

                $decision = $decisions[$month->budget_id]
                    ?? throw new InvalidArgumentException("Missing decision for budget {$month->budget_id}.");

                if (! in_array($decision, ['carry', 'reset'], true)) {
                    throw new InvalidArgumentException("Invalid decision '{$decision}'.");
                }

                $carry = $decision === 'carry' ? $month->balance() : 0; // can be negative

                $month->update(['carry_decision' => $decision, 'closed_at' => now()]);

                $this->openNextMonth($month->budget, $next, $carry);
            }
        });
    }

    private function openNextMonth(Budget $budget, CarbonInterface $next, int $carry): void
    {
        if ($budget->archived_at) {
            return;
        }

        $nextMonth = BudgetMonth::firstOrNew([
            'budget_id' => $budget->id,
            'period_start' => $next->toDateString(),
        ]);

        // Keep an amount already set for next month; otherwise reuse the default.
        if (! $nextMonth->exists) {
            $nextMonth->allocated = $budget->repeat_monthly ? $budget->default_amount : 0;
        }

        $nextMonth->carried_in = $carry;
        $nextMonth->save();
    }
}
