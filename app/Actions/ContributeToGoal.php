<?php

namespace App\Actions;

use App\Enums\GoalMovementTypeEnum;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use App\Support\EntryTime;
use App\Support\Periods;
use Illuminate\Validation\ValidationException;

class ContributeToGoal
{
    /** Adds money from outside the budget; a Budget source arrives in a later ticket. */
    public function handle(
        Household $household,
        User $by,
        Goal $goal,
        int $amount,
        ?string $when = null,
        ?string $note = null
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

        return $goal->movements()->create([
            'household_id' => $household->id,
            'user_id' => $by->id,
            'type' => GoalMovementTypeEnum::CONTRIBUTION,
            'amount' => $amount,
            'note' => $note,
            'occurred_at' => $date,
            'period_start' => $period->key(),
        ]);
    }
}
