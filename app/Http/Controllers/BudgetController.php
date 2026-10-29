<?php

namespace App\Http\Controllers;

use App\Actions\SaveBudgets;
use App\Http\Requests\SaveBudgetsRequest;
use App\Models\Budget;
use App\Models\Household;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function edit(Request $request, Household $household): Response
    {
        $period = $this->period($request->query('month'));

        $budgets = $household->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $period->toDateString())])
            ->get();

        return Inertia::render('budgets/edit', [
            'household' => ['id' => $household->id, 'currency' => $household->currency],
            'period' => $period->format('Y-m'),
            'periods' => collect(range(0, 2))->map(fn ($i) => [
                'value' => now()->startOfMonth()->addMonthsNoOverflow($i)->format('Y-m'),
                'label' => now()->startOfMonth()->addMonthsNoOverflow($i)->format('F Y'),
            ])->all(),
            'budgets' => $budgets->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'amount' => $b->months->first()?->allocated ?? $b->default_amount,
            ])->values(),
            'repeat' => $budgets->isEmpty() || $budgets->every->repeat_monthly,
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function update(SaveBudgetsRequest $request, Household $household, SaveBudgets $save): RedirectResponse
    {
        $save->handle($household, $request->period(), $request->validated('budgets'), $request->boolean('repeat'));

        return redirect()->route('budgets.edit', ['household' => $household, 'month' => $request->input('period')]);
    }

    public function show(Request $request, Household $household, Budget $budget): Response
    {
        $requested = $request->query('month');
        $period = ($requested && preg_match('/^\d{4}-\d{2}$/', $requested))
            ? Carbon::parse($requested.'-01')->min(now()->startOfMonth()) // no future months
            : now()->startOfMonth();

        $month = $budget->months()
            ->whereDate('period_start', $period->toDateString())
            ->with('transactions.user')
            ->first();

        abort_unless($month, 404);

        return Inertia::render('budgets/show', [
            'household' => ['id' => $household->id],
            'budget' => ['id' => $budget->id, 'name' => $budget->name],
            'monthLabel' => $period->format('F Y'),
            'closed' => $month->isClosed(),
            'summary' => $month->summary(),
            'transactions' => $month->transactions->sortByDesc('occurred_at')->values()->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->note ?: $budget->name,
                'user' => $t->user->name,
                'signed' => $t->signedAmount(),
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ]),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    private function period(?string $month): CarbonInterface
    {
        $min = now()->startOfMonth();

        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $min;
        }

        return Carbon::parse($month.'-01')->max($min);
    }
}
