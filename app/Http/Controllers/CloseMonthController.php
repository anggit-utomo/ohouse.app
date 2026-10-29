<?php

namespace App\Http\Controllers;

use App\Actions\CloseMonth;
use App\Models\Household;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CloseMonthController extends Controller
{
    public function store(Request $request, Household $household, CloseMonth $close): RedirectResponse
    {
        abort_unless(
            $household->members()->whereKey($request->user())->wherePivotIn('role', ['owner', 'editor'])->exists(),
            403,
        );

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'decisions' => ['required', 'array'],
            'decisions.*' => ['in:carry,reset'],
        ]);

        $close->handle($household, Carbon::parse($data['period'].'-01'), $data['decisions']);

        return back();
    }
}
