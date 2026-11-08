<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsGoalMovements;
use App\Enums\GoalMovementTypeEnum;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteGoal
{
    use RecordsGoalMovements;

    public function __construct(private SaveTransaction $transactions) {}

    /**
     * Empties a Goal through one final Withdrawal, then archives it. Archiving
     * is the only way to retire a Goal; it is never deleted.
     */
    public function handle(
        Household $household,
        User $by,
        Goal $goal,
        ?int $budgetId = null,
        ?string $note = null
    ): ?GoalMovement {
        if ($goal->household_id !== $household->id || $goal->archived_at !== null) {
            throw ValidationException::withMessages(['goal' => 'That goal is not open.']);
        }

        $destination = $this->goalBudget($household, $budgetId);

        return DB::transaction(function () use ($household, $by, $goal, $destination, $note) {
            $lockedHousehold = Household::lockForUpdate()->findOrFail($household->id);
            [$date, $period] = $this->moment($lockedHousehold, null);
            $locked = Goal::lockForUpdate()->with('movements')->findOrFail($goal->id);

            if ($locked->archived_at !== null) {
                throw ValidationException::withMessages(['goal' => 'That goal is not open.']);
            }

            $balance = $locked->balance();
            $movement = null;

            if ($balance > 0) {
                $month = $destination instanceof Budget
                    ? $this->transactions->openMonth($destination, $date)
                    : null;

                $movement = $this->record(
                    $locked,
                    $household,
                    $by,
                    GoalMovementTypeEnum::WITHDRAWAL,
                    $balance,
                    $date,
                    $period,
                    $month,
                    $note,
                );
            }

            // Ticket 07: delete this Goal's Sweep rules here, before archiving it.

            $locked->update(['archived_at' => now()]);

            return $movement;
        });
    }
}
