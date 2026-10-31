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
        $hasPrevious = $previous && $previous['has_data'];

        return Inertia::render('report/show', [
            'household' => ['id' => $household->id],
            'month' => $period->key(),
            'months' => $periods->take(12)
                ->map(
                    fn (Period $p) => ['value' => $p->key(), 'label' => $p->label()]
                )->values()->all(),
            'hasData' => $current['has_data'],
            'spent' => $current['spent'],
            'income' => $current['income'],
            'previous' => $hasPrevious ? ['label' => $before->title(), 'spent' => $previous['spent']] : null,
            'change' => $hasPrevious ? $current['spent'] - $previous['spent'] : null,
            'budgets' => $current['budgets'],
            'people' => $current['people'],
        ]);
    }
}
