<?php

namespace App\Support;

use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * @phpstan-type Row array{
 *     id: int,
 *     title: string,
 *     user: string,
 *     user_id: int,
 *     type: string,
 *     amount: int,
 *     note: string|null,
 *     budget: string,
 *     budget_id: int,
 *     signed: int,
 *     occurred_at: string,
 *     left: int,
 *     transfer_id: string|null
 * }
 */
final class TransactionRow
{
    /**
     * A single leg, as shown on a budget's detail page.
     *
     * @return Row
     */
    public static function forBudget(Transaction $transaction, BudgetMonth $month, Budget $budget): array
    {
        return self::make(
            $transaction,
            title: $transaction->note ?: $budget->name,
            budget: $budget->name,
            budgetId: $budget->id,
            signed: $transaction->signedAmount(),
            left: $month->balance(),
        );
    }

    /**
     * History and Home rows: a transfer collapses to one row for the whole move.
     * Callers order the entries first; this keeps that order.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Row>
     */
    public static function forHistoryList(Collection $transactions): Collection
    {
        $pairs = Transaction::whereIn('transfer_id', $transactions->pluck('transfer_id')->filter()->unique())
            ->with('budgetMonth.budget')->get()->groupBy('transfer_id');

        $moveTitle = function (Transaction $t) use ($pairs): ?string {
            if ($t->transfer_id === null) {
                return null;
            }

            $pair = $pairs[$t->transfer_id] ?? collect();
            $from = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_OUT)?->budgetMonth->budget->name;
            $to = $pair->firstWhere('type', TransactionTypeEnum::TRANSFER_IN)?->budgetMonth->budget->name;

            return "Moved {$from} → {$to}";
        };

        return $transactions
            ->unique(fn (Transaction $t) => $t->transfer_id ?? "t{$t->id}")
            ->values()
            ->map(fn (Transaction $t) => self::forHistory($t, $moveTitle($t)));
    }

    /**
     * A history row: a transfer collapses to one row for the whole move.
     *
     * @return Row
     */
    public static function forHistory(Transaction $transaction, ?string $transferTitle): array
    {
        $isTransfer = $transaction->transfer_id !== null;
        $budget = $transaction->budgetMonth->budget;

        return self::make(
            $transaction,
            title: $isTransfer ? ($transferTitle ?? $budget->name) : ($transaction->note ?: $budget->name),
            budget: $isTransfer ? 'Transfer' : $budget->name,
            budgetId: $transaction->budgetMonth->budget_id,
            signed: $isTransfer ? 0 : $transaction->signedAmount(),
            left: $transaction->budgetMonth->balance(),
        );
    }

    /** @return Row */
    private static function make(
        Transaction $transaction,
        string $title,
        string $budget,
        int $budgetId,
        int $signed,
        int $left,
    ): array {
        return [
            'id' => $transaction->id,
            'title' => $title,
            'user' => $transaction->user->name,
            'user_id' => $transaction->user_id,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'note' => $transaction->note,
            'budget' => $budget,
            'budget_id' => $budgetId,
            'signed' => $signed,
            'occurred_at' => $transaction->occurred_at->toIso8601String(),
            'left' => $left,
            'transfer_id' => $transaction->transfer_id,
        ];
    }
}
