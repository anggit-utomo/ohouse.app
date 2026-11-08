<?php

namespace App\Http\Controllers;

use App\Actions\SaveGoal;
use App\Http\Requests\SaveGoalRequest;
use App\Models\Goal;
use App\Models\Household;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request, Household $household): Response
    {
        $goals = $this->ordered($household);

        return Inertia::render('goals/index', [
            'household' => ['id' => $household->id],
            'goals' => $goals->map(fn (Goal $goal) => $goal->row())->values(),
            'saved' => $goals->sum(fn (Goal $goal) => $goal->balance()),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function store(SaveGoalRequest $request, Household $household, SaveGoal $save): RedirectResponse
    {
        $save->create($household, $request->validated());

        return back()->with('success', 'Goal created.');
    }

    public function show(Request $request, Household $household, Goal $goal): Response
    {
        return Inertia::render('goals/show', [
            'household' => ['id' => $household->id],
            'goal' => [
                ...$goal->row(),
                'target_date' => $goal->target_date?->toDateString(),
            ],
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

    /** @return Collection<int, Goal> */
    private function ordered(Household $household): Collection
    {
        return $household->goals()->active()->orderBy('sort')->orderBy('id')->get();
    }
}
