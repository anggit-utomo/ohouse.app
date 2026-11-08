<?php

namespace App\Http\Controllers;

use App\Actions\SaveGoal;
use App\Http\Requests\SaveGoalRequest;
use App\Models\Goal;
use App\Models\Household;
use App\Support\GoalLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request, Household $household): Response
    {
        $goals = $household->activeGoals();

        return Inertia::render('goals/index', [
            'household' => ['id' => $household->id],
            'goals' => $goals->map(fn (Goal $goal) => $goal->row())->values(),
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

        return Inertia::render('goals/show', [
            'household' => ['id' => $household->id],
            'goal' => [
                ...$goal->row(),
                'target_date' => $goal->target_date?->toDateString(),
            ],
            'movements' => $ledger['movements'],
            'savings' => $ledger['savings'],
            'today' => now()->toDateString(),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function update(
        SaveGoalRequest $request,
        Household $household,
        Goal $goal,
        SaveGoal $save
    ): RedirectResponse {
        $save->update($goal, $request->validated());

        return back()->with('success', 'Goal updated.');
    }
}
