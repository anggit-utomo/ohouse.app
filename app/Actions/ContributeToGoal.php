<?php

namespace App\Actions;

use App\Enums\GoalMovementTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use App\Support\Currencies;
use App\Support\EntryTime;
use App\Support\Periods;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContributeToGoal
{
    public function __construct(private SaveTransaction $transactions) {}

    /** Adds money from outside the budget, or from a Budget in the open Period. */
    public function handle(
        Household $household,
        User $by,
        Goal $goal,
        int $amount,
        ?string $when = null,
        ?string $note = null,
        ?int $budgetId = null
    ): GoalMovement {
        if ($goal->household_id !== $household->id || $goal->archived_at !== null) {
            throw ValidationException::withMessages(['goal' => 'That goal is not open.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        $date = EntryTime::at($when);
        $current = Periods::current($household);
        $period = Periods::containing($household, $date)
            ?? throw ValidationException::withMessages([
                'occurred_at' => 'There is no budget period for that date.',
            ]);

        // Compare the period itself, not just its end date, so a closed
        // period can never accept money even if its end was never written.
        if ($period->key() !== $current->key()) {
            throw ValidationException::withMessages([
                'occurred_at' => 'That period is closed. Add it to the current period instead.',
            ]);
        }

        $source = $budgetId === null
            ? null
            : ($household->budgets()->active()->find($budgetId)
                ?? throw ValidationException::withMessages(['budget_id' => 'Budget not found.']));

        return DB::transaction(function () use ($household, $by, $goal, $amount, $date, $note, $source, $period) {
            $month = null;
            $periodStart = $period->key();

            if ($source instanceof Budget) {
                $month = BudgetMonth::lockForUpdate()
                    ->with(['transactions', 'goalMovements'])
                    ->findOrFail($this->transactions->openMonth($source, $date)->id);

                if ($amount > $month->balance()) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            '%s only has %s left.',
                            $source->name,
                            Currencies::format(max($month->balance(), 0), $household->currency)
                        ),
                    ]);
                }

                $periodStart = $month->period_start->toDateString();
            }

            return $goal->movements()->create([
                'household_id' => $household->id,
                'user_id' => $by->id,
                'budget_month_id' => $month?->id,
                'type' => GoalMovementTypeEnum::CONTRIBUTION,
                'amount' => $amount,
                'note' => $note,
                'occurred_at' => $date,
                'period_start' => $periodStart,
            ]);
        });
    }
}
