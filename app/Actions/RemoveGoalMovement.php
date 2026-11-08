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
        if ($movement->period_start->toDateString() !== Periods::current($household)->key()) {
            throw ValidationException::withMessages([
                'movement' => 'That period is closed. Add a correction in the current period instead.',
            ]);
        }

        DB::transaction(function () use ($goal, $movement) {
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
