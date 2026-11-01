<?php

namespace App\Http\Controllers;

use App\Actions\MoveMoney;
use App\Http\Requests\MoveMoneyRequest;
use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function create(Request $request, Household $household): Response
    {
        $this->authorize('edit', $household);

        $budgets = $household->budgetBalances();
        $valid = fn (string $key) => $budgets->contains('id', $request->integer($key)) ? (string) $request->integer($key) : '';

        return Inertia::render('transfers/create', [
            'household' => ['id' => $household->id],
            'budgets' => $budgets,
            'defaults' => ['from_budget_id' => $valid('from'), 'to_budget_id' => $valid('to'), 'amount' => '', 'note' => ''],
        ]);
    }

    public function store(MoveMoneyRequest $request, Household $household, MoveMoney $move): RedirectResponse
    {
        $d = $request->validated();

        $move->handle($household, $request->user(), $d['from_budget_id'], $d['to_budget_id'], $d['amount'], $d['note'] ?? null);

        return redirect()->route('households.home', $household)
            ->with('success', 'Money moved.');
    }

    public function destroy(Household $household, string $transfer, MoveMoney $move): RedirectResponse
    {
        $rows = $household->transactions()->where('transfer_id', $transfer)->get();

        abort_if($rows->isEmpty(), 404);
        $this->authorize('delete', $rows->first());

        $move->undo($rows);

        return redirect()->route('transactions.index', $household)
            ->with('success', 'Move undone.');
    }
}
