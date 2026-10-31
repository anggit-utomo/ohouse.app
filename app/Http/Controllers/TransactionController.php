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
            // notes and budget names are encrypted, so searching happens here and not in SQL
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

        // both sides of a move are one row
        $rows = $rows->unique(fn (Transaction $t) => $t->transfer_id ?? "t{$t->id}")->values();

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
                'type' => $t->type->value,
                'title' => $t->transfer_id ? $moveTitle($t) : ($t->note ?: $t->budgetMonth->budget->name),
                'budget' => $t->transfer_id ? 'Move' : $t->budgetMonth->budget->name,
                'kind' => $t->transfer_id ? 'transfer' : $t->type->value,
                'signed' => $t->transfer_id ? 0 : $t->signedAmount(),
                'amount' => $t->amount,
                'occurred_at' => $t->occurred_at->toIso8601String(),
            ]),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    public function create(Request $request, Household $household): Response
    {
        $this->authorize('edit', $household);

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

        // "Add correction" from a locked transaction: prefill the opposite entry.
        if ($original = $household->transactions()->with('budgetMonth')->find($request->integer('corrects'))) {
            $budgetId = $original->budgetMonth->budget_id;
            $defaults = [
                ...$defaults,
                'type' => $original->type === TransactionTypeEnum::EXPENSE ? 'income' : 'expense',
                'budget_id' => $household->budgets()->active()->whereKey($budgetId)->exists() ? (string) $budgetId : '',
                'amount' => (string) $original->amount,
                'note' => 'Correction',
                'corrects_transaction_id' => $original->id,
            ];
        }

        return $this->form($household, 'create', $defaults);
    }

    public function store(TransactionRequest $request, Household $household, SaveTransaction $save): RedirectResponse
    {
        $transaction = $save->create($household, $request->validated());

        return redirect()->route('transactions.index', [
            $household,
            'month' => $transaction->budgetMonth->period_start->toDateString(),
        ]);
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
        ]);
    }

    public function edit(Household $household, Transaction $transaction): Response|RedirectResponse
    {
        $this->authorize('update', $transaction);

        if (
            $transaction->budgetMonth->isClosed() ||
            $transaction->type === TransactionTypeEnum::TRANSFER_IN ||
            $transaction->type === TransactionTypeEnum::TRANSFER_OUT
        ) {
            return redirect()->route('transactions.show', [$household, $transaction]);
        }

        return $this->form($household, 'edit', [
            'type' => $transaction->type->value,
            'budget_id' => (string) $transaction->budgetMonth->budget_id,
            'amount' => (string) $transaction->amount,
            'note' => $transaction->note ?? '',
            'occurred_at' => $transaction->occurred_at->toDateString(),
            'user_id' => (string) $transaction->user_id,
            'corrects_transaction_id' => $transaction->corrects_transaction_id,
        ], $transaction->id);
    }

    public function update(
        TransactionRequest $request,
        Household $household,
        Transaction $transaction,
        SaveTransaction $save
    ): RedirectResponse {
        $save->update($transaction, $request->validated());

        return redirect()->route('transactions.show', [$household, $transaction]);
    }

    public function destroy(Household $household, Transaction $transaction, SaveTransaction $save): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $month = $transaction->budgetMonth->period_start->toDateString();
        $save->delete($transaction);

        return redirect()->route('transactions.index', [$household, 'month' => $month]);
    }

    /** @param array<string, mixed> $defaults */
    private function form(Household $household, string $mode, array $defaults, ?int $id = null): Response
    {
        $period = now()->startOfMonth()->toDateString();

        $budgets = $household->budgets()->active()->orderBy('sort')
            ->with(['months' => fn ($q) => $q->whereDate('period_start', $period)->with('transactions')])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'left' => $b->months->first()?->balance() ?? ($b->repeat_monthly ? $b->default_amount : 0),
            ])->values();

        return Inertia::render('transactions/form', [
            'household' => ['id' => $household->id],
            'mode' => $mode,
            'transactionId' => $id,
            'budgets' => $budgets,
            'members' => $household->members()->get(['users.id', 'users.name'])->map->only(['id', 'name'])->values(),
            'defaults' => $defaults,
            'today' => now()->toDateString(),
        ]);
    }
}
