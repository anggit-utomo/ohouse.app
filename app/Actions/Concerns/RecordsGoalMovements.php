<?php

namespace App\Actions\Concerns;

use App\Enums\GoalMovementTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use App\Support\EntryTime;
use App\Support\Period;
use App\Support\Periods;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

trait RecordsGoalMovements
{
    protected function assertCanMove(Household $household, Goal $goal, int $amount): void
    {
        if ($goal->household_id !== $household->id || $goal->archived_at !== null) {
            throw ValidationException::withMessages(['goal' => 'That goal is not open.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
    }

    /**
     * The movement's date and the open period it belongs to. A closed period
     * is rejected by comparing the period key, not only its end date.
     *
     * @return array{0: CarbonImmutable, 1: Period}
     */
    protected function moment(Household $household, ?string $when): array
    {
        $date = EntryTime::at($when);
        $period = Periods::containing($household, $date)
            ?? throw ValidationException::withMessages([
                'occurred_at' => 'There is no budget period for that date.',
            ]);

        if (! Periods::isOpen($household, $period->key())) {
            throw ValidationException::withMessages([
                'occurred_at' => 'That period is closed. Add it to the current period instead.',
            ]);
        }

        return [$date, $period];
    }

    protected function goalBudget(Household $household, ?int $budgetId): ?Budget
    {
        return $budgetId === null
            ? null
            : ($household->budgets()->active()->find($budgetId)
                ?? throw ValidationException::withMessages(['budget_id' => 'Budget not found.']));
    }

    protected function record(
        Goal $goal,
        Household $household,
        User $by,
        GoalMovementTypeEnum $type,
        int $amount,
        CarbonImmutable $date,
        Period $period,
        ?BudgetMonth $month,
        ?string $note
    ): GoalMovement {
        return $goal->movements()->create([
            'household_id' => $household->id,
            'user_id' => $by->id,
            'budget_month_id' => $month?->id,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'occurred_at' => $date,
            'period_start' => $period->key(),
        ]);
    }
}
