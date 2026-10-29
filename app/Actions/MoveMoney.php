<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MoveMoney
{
    public function __construct(private SaveTransaction $transactions) {}

    /** Moves money between two budgets this month. Returns the transfer id shared by both entries. */
    public function handle(Household $household, User $by, int $fromId, int $toId, int $amount, ?string $note = null): string
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages(['to_budget_id' => 'Pick two different budgets.']);
        }

        $budgets = $household->budgets()->active()->whereIn('id', [$fromId, $toId])->get()->keyBy('id');

        if ($budgets->count() !== 2) {
            throw ValidationException::withMessages(['from_budget_id' => 'Budget not found.']);
        }

        return DB::transaction(function () use ($household, $by, $fromId, $toId, $amount, $note, $budgets) {
            $fromMonth = $this->transactions->openMonth($budgets[$fromId], now());
            $toMonth = $this->transactions->openMonth($budgets[$toId], now());

            // lock the source month so two people can't overspend it at the same moment
            $left = BudgetMonth::lockForUpdate()->with('transactions')->find($fromMonth->id)->balance();

            if ($amount > $left) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('%s only has Rp %s left.', $budgets[$fromId]->name, number_format(max($left, 0), 0, ',', '.')),
                ]);
            }

            $shared = [
                'household_id' => $household->id,
                'user_id' => $by->id,
                'amount' => $amount,
                'note' => $note,
                'occurred_at' => now(),
                'transfer_id' => $id = (string) Str::uuid(),
            ];

            $fromMonth->transactions()->create([...$shared, 'type' => 'transfer_out']);
            $toMonth->transactions()->create([...$shared, 'type' => 'transfer_in']);

            return $id;
        });
    }

    /** Deletes both sides of a move. */
    public function undo(Collection $rows): void
    {
        $rows->load('budgetMonth');

        if ($rows->contains(fn ($t) => $t->budgetMonth->isClosed())) {
            throw ValidationException::withMessages(['transfer' => 'That month is closed, so the move is locked.']);
        }

        DB::transaction(fn () => $rows->each->delete());
    }
}
