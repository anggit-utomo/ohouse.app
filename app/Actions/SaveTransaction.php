<?php

namespace App\Actions;

use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SaveTransaction
{
    /** @param array{budget_id:int,user_id:int,type:string,amount:int,note?:?string,occurred_at:string,corrects_transaction_id?:?int} $d */
    public function create(Household $household, array $d): Transaction
    {
        $budget = $household->budgets()->active()->findOrFail($d['budget_id']);
        $when = $this->when($d['occurred_at']);

        return $this->openMonth($budget, $when)->transactions()->create([
            'household_id' => $household->id,
            'user_id' => $d['user_id'],
            'type' => $d['type'],
            'amount' => $d['amount'],
            'note' => $d['note'] ?? null,
            'occurred_at' => $when,
            'corrects_transaction_id' => $d['corrects_transaction_id'] ?? null,
        ]);
    }

    public function update(Transaction $transaction, array $d): Transaction
    {
        $this->guardEditable($transaction);

        $budget = $transaction->household->budgets()->active()->findOrFail($d['budget_id']);
        $when = $this->when($d['occurred_at']);
        $month = $this->openMonth($budget, $when);

        $transaction->update([
            'budget_month_id' => $month->id,
            'user_id' => $d['user_id'],
            'type' => $d['type'],
            'amount' => $d['amount'],
            'note' => $d['note'] ?? null,
            'occurred_at' => $when,
        ]);

        return $transaction->unsetRelation('budgetMonth');
    }

    public function delete(Transaction $transaction): void
    {
        $this->guardEditable($transaction);
        $transaction->delete();
    }

    /** Today keeps the real time; other days are stored at noon. */
    private function when(string $date): CarbonInterface
    {
        $day = Carbon::parse($date);

        return $day->isToday() ? now() : $day->setTime(12, 0);
    }

    public function openMonth(Budget $budget, CarbonInterface $when): BudgetMonth
    {
        $period = $when->copy()->startOfMonth()->toDateString();

        $month = BudgetMonth::where('budget_id', $budget->id)->whereDate('period_start', $period)->first();

        if (! $month && $when->isSameMonth(now())) {
            $month = BudgetMonth::create([
                'budget_id' => $budget->id,
                'period_start' => $period,
                'allocated' => $budget->repeat_monthly ? $budget->default_amount : 0,
                'carried_in' => 0,
            ]);
        }

        if (! $month) {
            throw ValidationException::withMessages(['occurred_at' => 'There is no budget for that month.']);
        }

        if ($month->isClosed()) {
            throw ValidationException::withMessages(['occurred_at' => 'That month is closed. Add a correction dated today instead.']);
        }

        return $month;
    }

    private function guardEditable(Transaction $transaction): void
    {
        if (in_array($transaction->type, [TransactionTypeEnum::TRANSFER_IN, TransactionTypeEnum::TRANSFER_OUT], true)) {
            throw ValidationException::withMessages(['type' => 'Moved money cannot be edited here.']);
        }

        if ($transaction->budgetMonth->isClosed()) {
            throw ValidationException::withMessages(['occurred_at' => 'This month is closed. Add a correction instead.']);
        }
    }
}
