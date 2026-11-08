<?php

namespace App\Http\Controllers;

use App\Actions\CompleteGoal;
use App\Actions\SaveGoal;
use App\Http\Requests\CompleteGoalRequest;
use App\Http\Requests\SaveGoalRequest;
use App\Models\Goal;
use App\Models\Household;
use App\Support\GoalLedger;
use App\Support\Periods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request, Household $household): Response
    {
        $goals = $household->activeGoals();

        $archived = $household->goals()
            ->whereNotNull('archived_at')
            ->ordered()
            ->with('movements')
            ->get();

        return Inertia::render('goals/index', [
            'household' => ['id' => $household->id],
            'goals' => $goals->map(fn (Goal $goal) => $goal->row())->values(),
            'archived' => $archived->map(fn (Goal $goal) => $goal->archivedRow())->values(),
            'saved' => $goals->sum(fn (Goal $goal) => $goal->balance()),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function store(
        SaveGoalRequest $request,
        Household $household,
        SaveGoal $save
    ): RedirectResponse {
        $save->create($household, $request->validated());

        return back()->with('success', 'Goal created.');
    }

    public function show(Request $request, Household $household, Goal $goal): Response
    {
        $goal->load(['movements.user']);
        $ledger = GoalLedger::for($goal, $household);
        $archived = $goal->archived_at !== null;

        return Inertia::render('goals/show', [
            'household' => ['id' => $household->id],
            'goal' => [
                ...$goal->row(),
                'target_date' => $goal->target_date?->toDateString(),
            ],
            'movements' => $ledger['movements'],
            'savings' => $ledger['savings'],
            'budgets' => $household->budgetBalances(Periods::current($household)),
            'today' => now()->toDateString(),
            'archived' => $archived,
            'canEdit' => ! $archived && $request->user()->can('edit', $household),
        ]);
    }

    public function update(
        SaveGoalRequest $request,
        Household $household,
        Goal $goal,
        SaveGoal $save
    ): RedirectResponse {
        abort_if($goal->archived_at !== null, 403);

        $save->update($goal, $request->validated());

        return back()->with('success', 'Goal updated.');
    }

    public function complete(
        CompleteGoalRequest $request,
        Household $household,
        Goal $goal,
        CompleteGoal $complete
    ): RedirectResponse {
        $data = $request->validated();

        $complete->handle(
            $household,
            $request->user(),
            $goal,
            isset($data['budget_id']) ? (int) $data['budget_id'] : null,
            isset($data['note']) ? (string) $data['note'] : null,
        );

        return back()->with('success', "{$goal->name} is complete.");
    }
}
