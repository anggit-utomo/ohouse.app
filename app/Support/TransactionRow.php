<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Transaction;

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
 *     left: int
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
     * A history row: a transfer collapses to one row for the whole move.
     *
     * @return Row
     */
    public static function forHistory(Transaction $transaction, string $transferTitle): array
    {
        $isTransfer = $transaction->transfer_id !== null;
        $budget = $transaction->budgetMonth->budget;

        return self::make(
            $transaction,
            title: $isTransfer ? $transferTitle : ($transaction->note ?: $budget->name),
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
        ];
    }
}
