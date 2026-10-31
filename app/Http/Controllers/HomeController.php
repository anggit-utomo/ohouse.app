<?php

namespace App\Http\Controllers;

use App\Models\BudgetMonth;
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
            ->with(['budget', 'transactions'])
            ->get()
            ->sortBy(fn (BudgetMonth $m) => $m->budget->sort)
            ->values();

        $rows = $months->map(
            fn (BudgetMonth $m) => ['id' => $m->budget_id, 'name' => $m->budget->name, ...$m->summary()]
        );

        $spent = $rows->sum('spent');
        $available = $rows->sum('available');
        $canEdit = $user->can('edit', $household);
        $days = (int) abs($period->start->diffInDays(now()->startOfDay()));

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
            'members' => $household->members()
                ->get(['users.id', 'users.name'])
                ->map->only(['id', 'name'])
                ->values(),
            'canEdit' => $canEdit,
        ]);
    }
}
