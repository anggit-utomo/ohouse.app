<?php

namespace App\Http\Controllers;

use App\Actions\SaveBudgets;
use App\Http\Requests\SaveBudgetsRequest;
use App\Models\Budget;
use App\Models\Household;
use App\Support\Periods;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function edit(Request $request, Household $household): Response
    {
        $period = Periods::current($household);

        $budgets = $household->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $period->key())])
            ->get();

        return Inertia::render('budgets/edit', [
            'household' => ['id' => $household->id],
            'periodLabel' => $period->label(),
            'justStarted' => $request->boolean('new'),
            'budgets' => $budgets->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'amount' => $b->months->first()?->allocated ?? $b->default_amount,
                'repeat' => $b->repeat_monthly,
            ])->values(),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function update(
        SaveBudgetsRequest $request,
        Household $household,
        SaveBudgets $save
    ): RedirectResponse {
        $save->handle($household, array_values($request->validated('budgets')));

        return redirect()->route('budgets.edit', $household);
    }

    public function show(Request $request, Household $household, Budget $budget): Response
    {
        $period = Periods::find($household, $request->query('month'));

        $month = $budget->months()
            ->whereDate('period_start', $period->key())
            ->with('transactions.user')
            ->first();

        abort_if($month === null, 404);

        return Inertia::render('budgets/show', [
            'household' => ['id' => $household->id],
            'budget' => ['id' => $budget->id, 'name' => $budget->name],
            'monthLabel' => $period->label(),
            'closed' => $month->isClosed(),
            'summary' => $month->summary(),
            'transactions' => $month->transactions->sortByDesc('occurred_at')->values()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->note ?: $budget->name,
                    'user' => $t->user->name,
                    'signed' => $t->signedAmount(),
                    'occurred_at' => $t->occurred_at->toIso8601String(),
                ]),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }
}
