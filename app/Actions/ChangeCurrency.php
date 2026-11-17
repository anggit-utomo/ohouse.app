<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Support\Currencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeCurrency
{
    /**
     * Allowed only while nothing has been recorded.
     * Budget amounts keep the same displayed number.
     */
    public function handle(Household $household, string $code): void
    {
        $from = Currencies::exponent($household->currency);
        $to = Currencies::exponent($code);

        DB::transaction(function () use ($household, $code, $from, $to) {
            $locked = Household::lockForUpdate()->findOrFail($household->id);

            if ($locked->hasRecordedMoney()) {
                throw ValidationException::withMessages([
                    'currency' => 'The currency can\'t be changed after money has been recorded.',
                ]);
            }

            if ($from !== $to) {
                $scale = fn (int $amount) => $to > $from
                    ? $amount * 10 ** ($to - $from)
                    : intdiv($amount, 10 ** ($from - $to));

                $household->budgets()->chunkById(100, function ($budgets) use ($scale) {
                    foreach ($budgets as $budget) {
                        $budget->update(['default_amount' => $scale($budget->default_amount)]);
                    }
                });

                BudgetMonth::forHousehold($household)->chunkById(100, function ($months) use ($scale) {
                    foreach ($months as $month) {
                        $month->update([
                            'allocated' => $scale($month->allocated),
                            'carried_in' => $scale($month->carried_in),
                        ]);
                    }
                });

                $household->goals()->chunkById(100, function ($goals) use ($scale) {
                    foreach ($goals as $goal) {
                        $goal->update(['target_amount' => $scale($goal->target_amount)]);
                    }
                });
            }

            $household->update(['currency' => $code]);
        });
    }
}
