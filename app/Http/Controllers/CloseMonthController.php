<?php

namespace App\Http\Controllers;

use App\Actions\CloseMonth;
use App\Http\Requests\CloseMonthRequest;
use App\Models\Household;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CloseMonthController extends Controller
{
    public function show(Household $household, CloseMonth $close): Response|RedirectResponse
    {
        $this->authorize('edit', $household);

        $period = $close->pending($household);

        if (! $period) {
            return redirect()->route('households.home', $household);
        }

        $next = $period->copy()->addMonthNoOverflow();

        return Inertia::render('close/show', [
            'household' => ['id' => $household->id],
            'period' => $period->format('Y-m'),
            'periodLabel' => $period->format('F'),
            'nextLabel' => $next->format('F'),
            'closesOn' => '1 '.$next->format('F'),
            'canClose' => $close->canClose($period),
            'rows' => $close->preview($household, $period),
        ]);
    }

    public function store(CloseMonthRequest $request, Household $household, CloseMonth $close): RedirectResponse
    {
        $data = $request->validated();

        $close->handle($household, Carbon::parse($data['period'].'-01'), $data['decisions']);

        return redirect()->route('households.home', $household);
    }
}
