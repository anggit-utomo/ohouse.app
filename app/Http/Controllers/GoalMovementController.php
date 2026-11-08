<?php

namespace App\Http\Controllers;

use App\Actions\MoveToGoal;
use App\Http\Requests\SaveGoalMovementRequest;
use App\Models\Goal;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;

class GoalMovementController extends Controller
{
    public function store(
        SaveGoalMovementRequest $request,
        Household $household,
        Goal $goal,
        MoveToGoal $move
    ): RedirectResponse {
        $data = $request->validated();

        $move->contribute(
            $household,
            $request->user(),
            $goal,
            (int) $data['amount'],
            isset($data['occurred_at']) ? (string) $data['occurred_at'] : null,
            isset($data['note']) ? (string) $data['note'] : null,
        );

        return back()->with('success', "Money added to {$goal->name}.");
    }
}
