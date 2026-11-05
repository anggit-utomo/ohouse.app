<?php

namespace App\Http\Controllers;

use App\Actions\BuildReport;
use App\Models\Household;
use App\Support\Period;
use App\Support\Periods;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, Household $household, BuildReport $report): Response
    {
        $periods = Periods::all($household);
        $period = Periods::find($household, $request->query('month'));
        $before = $periods->first(fn (Period $p) => $p->start->lt($period->start));

        $current = $report->handle($household, $period);
        $previous = $before ? $report->handle($household, $before) : null;

        $previousSummary = $before !== null && $previous !== null && $previous['has_data']
            ? ['label' => $before->title(), 'spent' => $previous['spent']]
            : null;

        $previousBudgets = $previous !== null && $previous['has_data']
            ? collect($previous['budgets'])->keyBy('id')
            : collect();

        return Inertia::render('report/index', [
            'household' => ['id' => $household->id],
            'month' => $period->key(),
            'months' => $periods->take(12)
                ->map(
                    fn (Period $p) => ['value' => $p->key(), 'label' => $p->label()]
                )->values()->all(),
            'hasData' => $current['has_data'],
            'spent' => $current['spent'],
            'income' => $current['income'],
            'previous' => $previousSummary,
            'change' => $previousSummary !== null ? $current['spent'] - $previousSummary['spent'] : null,
            'dailySpending' => $current['daily_spending'],
            'busiestDay' => $current['busiest_day'],
            'budgets' => collect($current['budgets'])->map(function (array $b) use ($previousBudgets) {
                $match = $previousBudgets->get($b['id']);
                $before = $match === null ? 0 : $match['spent'];

                return [...$b, 'changePct' => $before > 0 ? (int) round(($b['spent'] - $before) / $before * 100) : null];
            })->all(),
            'biggestExpenses' => $current['biggest_expenses'],
            'people' => $current['people'],
        ]);
    }
}
