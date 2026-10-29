<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use Carbon\Carbon;
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
            ->whereHas('budget', fn ($q) => $q->where('household_id', $household->id))
            ->with(['budget', 'transactions'])
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();
    }

    /** Unclosed budget months of a period, in budget order. */
    public function open(Household $household, CarbonInterface $period): Collection
    {
        return $this->months($household, $period)
            ->reject(fn (BudgetMonth $m) => $m->isClosed() || $m->budget->archived_at)
            ->sortBy(fn (BudgetMonth $m) => [$m->budget->sort, $m->budget_id])
            ->values();
    }

    /** What the Close screen lists: what is left per budget and what next month starts at. */
    public function preview(Household $household, CarbonInterface $period): Collection
    {
        $next = $period->copy()->startOfMonth()->addMonthNoOverflow()->toDateString();

        return $this->open($household, $period)->map(fn (BudgetMonth $m) => [
            'budget_id' => $m->budget_id,
            'name' => $m->budget->name,
            'leftover' => $m->balance(),
            'next_allocated' => BudgetMonth::where('budget_id', $m->budget_id)->whereDate('period_start', $next)->first()?->allocated
                ?? ($m->budget->repeat_monthly ? $m->budget->default_amount : 0),
        ]);
    }

    /** The month waiting to be closed: the oldest unclosed past month, else the current month in its last 5 days. */
    public function pending(Household $household): ?CarbonInterface
    {
        $thisMonth = now()->startOfMonth();

        $unclosed = fn () => BudgetMonth::query()->whereNull('closed_at')
            ->whereHas('budget', fn ($q) => $q->active()->where('household_id', $household->id));

        $oldest = $unclosed()->whereDate('period_start', '<', $thisMonth->toDateString())->orderBy('period_start')->value('period_start');

        if ($oldest) {
            return Carbon::parse($oldest)->startOfMonth();
        }

        $inLastDays = now()->daysInMonth - now()->day <= 4;

        return $inLastDays && $unclosed()->whereDate('period_start', $thisMonth->toDateString())->exists() ? $thisMonth : null;
    }

    /** A month can be closed once it is over. */
    public function canClose(CarbonInterface $period): bool
    {
        return $period->copy()->endOfMonth()->isPast();
    }

    /**
     * @param  array<int, string>  $decisions  budget_id => 'carry' | 'reset'
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

        $nextMonth = BudgetMonth::where('budget_id', $budget->id)
            ->whereDate('period_start', $next->toDateString())
            ->first()
            ?? new BudgetMonth([
                'budget_id' => $budget->id,
                'period_start' => $next->toDateString(),
                'allocated' => $budget->repeat_monthly ? $budget->default_amount : 0,
            ]);

        $nextMonth->carried_in = $carry;
        $nextMonth->save();
    }
}
