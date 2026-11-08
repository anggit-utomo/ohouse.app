<?php

namespace App\Actions;

use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Support\Periods;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveGoalMovement
{
    /** Deletes a movement while its Period is still open. */
    public function handle(Household $household, Goal $goal, GoalMovement $movement): void
    {
        if ($movement->goal_id !== $goal->id || $movement->household_id !== $household->id) {
            throw ValidationException::withMessages([
                'movement' => 'That movement does not belong to this goal.',
            ]);
        }

        if ($goal->archived_at !== null) {
            throw ValidationException::withMessages([
                'goal' => 'That goal is not open.',
            ]);
        }

        DB::transaction(function () use ($household, $goal, $movement) {
            $lockedHousehold = Household::lockForUpdate()->findOrFail($household->id);

            if (! Periods::isOpen($lockedHousehold, $movement->period_start->toDateString())) {
                throw ValidationException::withMessages([
                    'movement' => 'That period is closed. Add a correction in the current period instead.',
                ]);
            }

            $locked = Goal::lockForUpdate()->with('movements')->findOrFail($goal->id);

            if ($locked->balance() - $movement->signedAmount() < 0) {
                throw ValidationException::withMessages([
                    'movement' => 'That would take the goal below zero.',
                ]);
            }

            $movement->delete();
        });
    }
}
