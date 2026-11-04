<?php

namespace App\Http\Controllers;

use App\Actions\SaveTransaction;
use App\Enums\TransactionTypeEnum;
use App\Http\Requests\TransactionRequest;
use App\Models\Household;
use App\Models\Transaction;
use App\Support\Periods;
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
            ->with(['budgetMonth.budget', 'user'])
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

        $moveTitle = function (Transaction $t) use ($pairs) {
            $pair = $pairs[$t->transfer_id] ?? collect();
            $from = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_OUT)?->budgetMonth->budget->name;
            $to = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_IN)?->budgetMonth->budget->name;

            return "Moved {$from} → {$to}";
        };

        $rows = $rows->unique(fn (Transaction $t) => $t->transfer_id ?? "t{$t->id}")->values();

        // FIX: Match BudgetController by querying months for $period->key() instead of hardcoded current month
        $periodKey = $period->key();
        $budgets = $household->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $periodKey)->with('transactions')])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance() ?? ($b->repeat_monthly ? $b->default_amount : 0),
            ])->values();

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
            'transactions' => $rows->map(fn (Transaction $t) => [
                'id' => $t->id,
                'user' => $t->user->name,
                'user_id' => $t->user_id,
                'type' => $t->type->value,
                'title' => $t->transfer_id ? $moveTitle($t) : ($t->note ?: $t->budgetMonth->budget->name),
                'note' => $t->note,
                'budget' => $t->transfer_id ? 'Move' : $t->budgetMonth->budget->name,
                'budget_id' => $t->budgetMonth->budget_id,
                'kind' => $t->transfer_id ? 'transfer' : $t->type->value,
                'signed' => $t->transfer_id ? 0 : $t->signedAmount(),
                'amount' => $t->amount,
                'occurred_at' => $t->occurred_at->toIso8601String(),
                'left' => $t->budgetMonth->balance(),
            ]),
            'canEdit' => $request->user()->can('edit', $household),
            'budgets' => $budgets,
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

    public function show(Household $household, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        $transaction->load(['budgetMonth.transactions', 'budgetMonth.budget', 'user']);
        $month = $transaction->budgetMonth;
        $isTransfer = in_array(
            $transaction->type,
            [TransactionTypeEnum::TRANSFER_IN, TransactionTypeEnum::TRANSFER_OUT],
            true
        );

        $moved = null;

        if ($isTransfer && $transaction->transfer_id) {
            $pair = Transaction::where('transfer_id', $transaction->transfer_id)->with('budgetMonth.budget')->get();
            $moved = [
                'from' => $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_OUT)?->budgetMonth->budget->name,
                'to' => $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_IN)?->budgetMonth->budget->name,
            ];
        }

        // FIX: Match period start with the transaction's budget month start date
        $periodKey = $month->period_start->toDateString();
        $budgets = $household->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $periodKey)->with('transactions')])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance() ?? ($b->repeat_monthly ? $b->default_amount : 0),
            ])->values();

        $defaults = [
            'type' => $transaction->type->value,
            'budget_id' => (string) $transaction->budgetMonth->budget_id,
            'amount' => (string) $transaction->amount,
            'note' => $transaction->note ?? '',
            'occurred_at' => $transaction->occurred_at->toDateString(),
            'user_id' => (string) $transaction->user_id,
            'corrects_transaction_id' => $transaction->corrects_transaction_id,
        ];

        return Inertia::render('transactions/show', [
            'household' => ['id' => $household->id],
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'note' => $transaction->note,
                'budget' => $month->budget->name,
                'user' => $transaction->user->name,
                'occurred_at' => $transaction->occurred_at->toIso8601String(),
                'left' => $month->balance(),
            ],
            'locked' => $month->isClosed(),
            'isTransfer' => $isTransfer,
            'canEdit' => request()->user()->can('update', $transaction),
            'moved' => $moved,
            'transferId' => $transaction->transfer_id,
            'budgets' => $budgets,
            'members' => $household->members()
                ->get(['users.id', 'users.name'])
                ->map->only(['id', 'name'])
                ->values(),
            'defaults' => $defaults,
            'today' => now()->toDateString(),
        ]);
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
