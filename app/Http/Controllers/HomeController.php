<?php

namespace App\Http\Controllers;

use App\Enums\TransactionTypeEnum;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\Household;
use App\Support\Periods;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, Household $household): Response
    {
        $user = $request->user();

        if ($user->current_household_id !== $household->id) {
            $user->forceFill(['current_household_id' => $household->id])->save();
        }

        $period = Periods::current($household);

        $months = BudgetMonth::query()
            ->whereDate('period_start', $period->key())
            ->whereHas('budget', fn ($q) => $q->active()->where('household_id', $household->id))
            ->with(['budget', 'transactions', 'goalMovements'])
            ->get()
            ->sortBy(fn (BudgetMonth $m) => $m->budget->sort)
            ->values();

        $rows = $months->map(
            fn (BudgetMonth $m) => [
                'id' => $m->budget_id,
                'name' => $m->budget->name,
                ...$m->summary(),
            ]
        );

        $spent = $rows->sum('spent');
        $available = $rows->sum('available');
        $canEdit = $user->can('edit', $household);
        $days = (int) abs($period->start->diffInDays(now()->startOfDay()));

        $goals = $household->activeGoals();

        // for transaction form in homepage
        $preselected = $household->budgets()->active()
            ->whereKey($request->integer('budget'))
            ->value('id');
        $defaults = [
            'type' => 'expense',
            'budget_id' => $preselected ? (string) $preselected : '',
            'amount' => '',
            'note' => '',
            'occurred_at' => now()->toDateString(),
            'user_id' => (string) $request->user()->id,
            'corrects_transaction_id' => null,
        ];

        // "Add correction" from a locked transaction: prefill the opposite entry.
        if ($original = $household->transactions()->with('budgetMonth')
            ->find($request->integer('corrects'))
        ) {
            $budgetId = $original->budgetMonth->budget_id;
            $defaults = [
                ...$defaults,
                'type' => $original->type === TransactionTypeEnum::EXPENSE ? 'income' : 'expense',
                'budget_id' => $household->budgets()->active()->whereKey($budgetId)->exists()
                    ? (string) $budgetId
                    : '',
                'amount' => (string) $original->amount,
                'note' => 'Correction',
                'corrects_transaction_id' => $original->id,
            ];
        }
        // end transaction form in homepage

        return Inertia::render('home', [
            'household' => ['id' => $household->id],
            'monthLabel' => $period->title(),
            'periodRange' => $period->range(),
            'summary' => [
                'left' => $rows->sum('left'),
                'spent' => $spent,
                'available' => $available,
                'pct' => $available > 0 ? min(100, (int) round($spent / $available * 100)) : 0,
            ],
            // a gentle reminder once the period has run 25 days
            'banner' => $canEdit && $days >= 25 ? ['days' => $days] : null,
            'budgets' => $rows,
            'goals' => $goals->map(fn (Goal $g) => $g->row())->values(),
            'saved' => $goals->sum(fn (Goal $g) => $g->balance()),
            'members' => $household->members()
                ->get(['users.id', 'users.name'])
                ->map->only(['id', 'name'])
                ->values(),
            'canEdit' => $canEdit,
            'defaults' => $defaults,
            'today' => now()->toDateString(),
        ]);
    }
}
