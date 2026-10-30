<?php

namespace App\Http\Controllers;

use App\Actions\BuildReport;
use App\Models\Household;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, Household $household, BuildReport $report): Response
    {
        $month = $this->month($request->query('month'));
        $previousMonth = $month->copy()->subMonthNoOverflow();

        $current = $report->handle($household, $month);
        $previous = $report->handle($household, $previousMonth);

        return Inertia::render('report/show', [
            'household' => ['id' => $household->id],
            'month' => $month->format('Y-m'),
            'months' => collect(range(0, 5))->map(fn ($i) => [
                'value' => now()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m'),
                'label' => now()->startOfMonth()->subMonthsNoOverflow($i)->format('F Y'),
            ])->all(),
            'hasData' => $current['has_data'],
            'spent' => $current['spent'],
            'income' => $current['income'],
            'previous' => $previous['has_data']
                ? ['label' => $previousMonth->format('F'), 'spent' => $previous['spent']]
                : null,
            'change' => $previous['has_data'] ? $current['spent'] - $previous['spent'] : null,
            'budgets' => $current['budgets'],
            'people' => $current['people'],
        ]);
    }

    private function month(?string $month): CarbonInterface
    {
        $max = now()->startOfMonth();

        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $max;
        }

        return Carbon::parse($month.'-01')->min($max); // no future months
    }
}
