<?php

namespace App\Http\Controllers;

use App\Actions\SaveTransaction;
use App\Enums\TransactionTypeEnum;
use App\Http\Requests\TransactionRequest;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\Periods;
use App\Support\TransactionRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request, Household $household): Response
    {
        $period = Periods::find($household, $request->query('month'));
        $type = in_array($request->query('type'), ['expense', 'income'], true) ? $request->query('type') : null;
        $person = $request->integer('person') ?: null;
        $q = trim((string) $request->query('q', ''));

        $rows = $household->transactions()
            ->with(['budgetMonth.budget', 'budgetMonth.transactions', 'user'])
            ->whereBetween('occurred_at', [$period->start->startOfDay(), $period->endOrNow()])
            ->when($type, fn ($x) => $x->where('type', $type))
            ->when($person, fn ($x) => $x->where('user_id', $person))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->get()
            ->when($q !== '', fn ($c) => $c->filter(
                fn (Transaction $t) => Str::contains(($t->note ?? '').' '.$t->budgetMonth->budget->name, $q, ignoreCase: true)
            ))
            ->values();

        $pairs = Transaction::whereIn('transfer_id', $rows->pluck('transfer_id')->filter()->unique())
            ->with('budgetMonth.budget')->get()->groupBy('transfer_id');

        $moveTitle = function (Transaction $t) use ($pairs): string {
            $pair = $pairs[$t->transfer_id] ?? collect();
            $from = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_OUT)?->budgetMonth->budget->name;
            $to = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_IN)?->budgetMonth->budget->name;

            return "Moved {$from} → {$to}";
        };

        $rows = $rows->unique(fn (Transaction $t) => $t->transfer_id ?? "t{$t->id}")->values();

        $preselected = $household->budgets()->active()->whereKey($request->integer('budget'))->value('id');
        $defaults = [
            'type' => 'expense',
            'budget_id' => $preselected ? (string) $preselected : '',
            'amount' => '',
            'note' => '',
            'occurred_at' => now()->toDateString(),
            'user_id' => (string) $request->user()->id,
            'corrects_transaction_id' => null,
        ];

        return Inertia::render('transactions/index', [
            'household' => ['id' => $household->id],
            'month' => $period->key(),
            'closed' => ! $period->isCurrent(),
            'months' => Periods::all($household)
                ->take(12)
                ->map(fn ($p) => ['value' => $p->key(), 'label' => $p->label()])
                ->values()
                ->all(),
            'filters' => ['type' => $type, 'person' => $person, 'q' => $q],
            'members' => $household->members()->get(['users.id', 'users.name'])->map->only(['id', 'name'])->values(),
            'totals' => [
                'expense' => $rows->filter(fn ($t) => $t->type === TransactionTypeEnum::EXPENSE)->sum('amount'),
                'income' => $rows->filter(fn ($t) => $t->type === TransactionTypeEnum::INCOME)->sum('amount'),
            ],
            'transactions' => $rows->map(fn (Transaction $t) => TransactionRow::forHistory(
                $t,
                $t->transfer_id !== null ? $moveTitle($t) : null,
            )),
            'canEdit' => $request->user()->can('edit', $household),
            'budgets' => $household->budgetBalances($period),
            'defaults' => $defaults,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(TransactionRequest $request, Household $household, SaveTransaction $save): RedirectResponse
    {
        $transaction = $save->create($household, $request->validated(), $request->user());

        return back()->with(
            'success',
            $transaction->type === TransactionTypeEnum::INCOME ? 'Income saved.' : 'Expense saved.'
        );
    }

    public function update(
        TransactionRequest $request,
        Household $household,
        Transaction $transaction,
        SaveTransaction $save
    ): RedirectResponse {
        $save->update($transaction, $request->validated());

        return back()->with('success', 'Transaction updated.');
    }

    public function destroy(Household $household, Transaction $transaction, SaveTransaction $save): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $month = $transaction->budgetMonth->period_start->toDateString();
        $save->delete($transaction);

        return redirect()->route('transactions.index', [$household, 'month' => $month])
            ->with('success', 'Transaction deleted.');
    }
}
