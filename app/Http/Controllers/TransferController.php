<?php

namespace App\Http\Controllers;

use App\Actions\MoveMoney;
use App\Http\Requests\MoveMoneyRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;

class TransferController extends Controller
{
    public function store(MoveMoneyRequest $request, Household $household, MoveMoney $move): RedirectResponse
    {
        $d = $request->validated();

        $move->handle($household, $request->user(), $d['from_budget_id'], $d['to_budget_id'], $d['amount'], $d['note'] ?? null);

        return back()->with('success', 'Money moved.');
    }

    public function destroy(Household $household, string $transfer, MoveMoney $move): RedirectResponse
    {
        $rows = $household->transactions()->where('transfer_id', $transfer)->get();

        abort_if($rows->isEmpty(), 404);
        $this->authorize('delete', $rows->first());

        $move->undo($rows);

        return back()->with('success', 'Move undone.');
    }
}
