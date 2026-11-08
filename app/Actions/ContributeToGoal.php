<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsGoalMovements;
use App\Enums\GoalMovementTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContributeToGoal
{
    use RecordsGoalMovements;

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
        $this->assertCanMove($household, $goal, $amount);

        $sourceBudget = $this->goalBudget($household, $budgetId);

        return DB::transaction(function () use ($household, $by, $goal, $amount, $when, $note, $sourceBudget) {
            $lockedHousehold = Household::lockForUpdate()->findOrFail($household->id);
            [$date, $period] = $this->moment($lockedHousehold, $when);
            $month = null;

            if ($sourceBudget instanceof Budget) {
                $sourceMonth = $this->transactions->openMonth($sourceBudget, $date);

                $month = BudgetMonth::lockForUpdate()
                    ->with(['budget'])
                    ->withMoney()
                    ->findOrFail($sourceMonth->id);

                if ($message = $month->shortfallMessage($amount, $household->currency)) {
                    throw ValidationException::withMessages(['amount' => $message]);
                }
            }

            return $this->record(
                $goal,
                $household,
                $by,
                GoalMovementTypeEnum::CONTRIBUTION,
                $amount,
                $date,
                $period,
                $month,
                $note,
            );
        });
    }
}
