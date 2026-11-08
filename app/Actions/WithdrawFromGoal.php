<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsGoalMovements;
use App\Enums\GoalMovementTypeEnum;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawFromGoal
{
    use RecordsGoalMovements;

    public function __construct(private SaveTransaction $transactions) {}

    /** Takes money out to outside the budget, or back into a Budget in the open Period. */
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

        [$date, $period] = $this->moment($household, $when);
        $destination = $this->goalBudget($household, $budgetId);

        return DB::transaction(function () use ($household, $by, $goal, $amount, $date, $note, $destination, $period) {
            $locked = Goal::lockForUpdate()->with('movements')->findOrFail($goal->id);

            if ($amount > $locked->balance()) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Only %s is saved.',
                        Currencies::format($locked->balance(), $household->currency)
                    ),
                ]);
            }

            $month = $destination instanceof Budget
                ? $this->transactions->openMonth($destination, $date)
                : null;

            return $this->record(
                $locked,
                $household,
                $by,
                GoalMovementTypeEnum::WITHDRAWAL,
                $amount,
                $date,
                $period,
                $month,
                $note,
            );
        });
    }
}
