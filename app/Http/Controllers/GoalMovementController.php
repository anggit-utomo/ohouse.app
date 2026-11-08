<?php

namespace App\Http\Controllers;

use App\Actions\ContributeToGoal;
use App\Actions\RemoveGoalMovement;
use App\Actions\WithdrawFromGoal;
use App\Enums\GoalMovementTypeEnum;
use App\Http\Requests\SaveGoalMovementRequest;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;

class GoalMovementController extends Controller
{
    public function store(
        SaveGoalMovementRequest $request,
        Household $household,
        Goal $goal,
        ContributeToGoal $contribute,
        WithdrawFromGoal $withdraw
    ): RedirectResponse {
        $data = $request->validated();
        $type = $data['type'];

        $amount = (int) $data['amount'];
        $when = isset($data['occurred_at']) ? (string) $data['occurred_at'] : null;
        $note = isset($data['note']) ? (string) $data['note'] : null;
        $budgetId = isset($data['budget_id']) ? (int) $data['budget_id'] : null;

        if ($type === GoalMovementTypeEnum::WITHDRAWAL->value) {
            $withdraw->handle($household, $request->user(), $goal, $amount, $when, $note, $budgetId);

            return back()->with('success', "Money taken out of {$goal->name}.");
        }

        $contribute->handle($household, $request->user(), $goal, $amount, $when, $note, $budgetId);

        return back()->with('success', "Money added to {$goal->name}.");
    }

    public function destroy(
        Household $household,
        Goal $goal,
        GoalMovement $movement,
        RemoveGoalMovement $remove
    ): RedirectResponse {
        $this->authorize('edit', $household);

        $remove->handle($household, $goal, $movement);

        return back()->with('success', 'Movement removed.');
    }
}
