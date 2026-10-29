<?php

namespace App\Http\Controllers;

use App\Actions\CloseMonth;
use App\Models\BudgetMonth;
use App\Models\Household;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, Household $household, CloseMonth $close): Response
    {
        $user = $request->user();

        if ($user->current_household_id !== $household->id) {
            $user->forceFill(['current_household_id' => $household->id])->save();
        }

        $months = BudgetMonth::query()
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->whereHas('budget', fn ($q) => $q->active()->where('household_id', $household->id))
            ->with(['budget', 'transactions'])
            ->get()
            ->sortBy(fn (BudgetMonth $m) => $m->budget->sort)
            ->values();

        $rows = $months->map(fn (BudgetMonth $m) => ['id' => $m->budget_id, 'name' => $m->budget->name, ...$m->summary()]);

        $spent = $rows->sum('spent');
        $available = $rows->sum('available');
        $daysLeft = now()->daysInMonth - now()->day; // 0 on the last day
        $canEdit = $user->can('edit', $household);

        $pending = $canEdit ? $close->pending($household) : null;
        $overdue = $pending?->lt(now()->startOfMonth()) ?? false;

        return Inertia::render('home', [
            'household' => ['id' => $household->id],
            'monthLabel' => now()->format('F Y'),
            'summary' => [
                'left' => $rows->sum('left'),
                'spent' => $spent,
                'available' => $available,
                'pct' => $available > 0 ? min(100, (int) round($spent / $available * 100)) : 0,
            ],
            // last 5 days of the month, only for people who can close it, and only while something is still open
            'banner' => $pending ? [
                'label' => $pending->format('F'),
                'overdue' => $overdue,
                'daysLeft' => $overdue ? null : $daysLeft,
            ] : null,
            'budgets' => $rows,
            'members' => $household->members()->get(['users.id', 'users.name'])->map->only(['id', 'name'])->values(),
            'canEdit' => $canEdit,
        ]);
    }
}
