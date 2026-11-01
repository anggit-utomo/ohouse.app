<?php

namespace App\Http\Controllers;

use App\Actions\CloseMonth;
use App\Http\Requests\CloseMonthRequest;
use App\Models\Household;
use App\Support\Periods;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CloseMonthController extends Controller
{
    public function show(Request $request, Household $household, CloseMonth $close): Response
    {
        $this->authorize('edit', $household);

        $current = Periods::current($household);
        $date = $this->date($request->query('date'));
        $preview = $close->preview($household, $date);

        return Inertia::render('close/show', [
            'household' => ['id' => $household->id],
            'currentTitle' => $current->title(),
            'currentRange' => $current->range(),
            'startDate' => $date->toDateString(),
            'minDate' => $current->start->addDay()->toDateString(),
            'maxDate' => now()->toDateString(),
            'rows' => $preview['rows'],
            'ending' => $preview['ending'],
            'canStart' => $date->gt($current->start),
        ]);
    }

    public function store(
        CloseMonthRequest $request,
        Household $household,
        CloseMonth $close
    ): RedirectResponse {
        $data = $request->validated();

        $close->handle($household, Carbon::parse($data['start']), $data['decisions']);

        // straight to Set budgets: repeating budgets are filled in, add the one-off ones
        return redirect()->route('budgets.edit', [$household, 'new' => 1])
            ->with('success', 'New period started.');
    }

    /** Defaults to today and never goes into the future. */
    private function date(?string $value): CarbonInterface
    {
        $today = now()->startOfDay();
        $date = ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
            ? Carbon::parse($value)->startOfDay()
            : $today;

        return $date->gt($today) ? $today : $date;
    }
}
