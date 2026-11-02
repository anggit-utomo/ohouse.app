<?php

namespace App\Actions;

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Support\Currencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeCurrency
{
    /** Allowed only while nothing has been recorded. Budget amounts keep the same displayed number. */
    public function handle(Household $household, string $code): void
    {
        if ($household->transactions()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'currency' => 'The currency can\'t be changed after transactions have been recorded.',
            ]);
        }

        $from = Currencies::exponent($household->currency);
        $to = Currencies::exponent($code);

        DB::transaction(function () use ($household, $code, $from, $to) {
            if ($from !== $to) {
                $scale = fn (int $amount) => $to > $from
                    ? $amount * 10 ** ($to - $from)
                    : intdiv($amount, 10 ** ($from - $to));

                $household->budgets()->get()->each(
                    fn ($b) => $b->update(
                        [
                            'default_amount' => $scale($b->default_amount),
                        ]
                    )
                );

                BudgetMonth::forHousehold($household)->get()->each(
                    fn ($m) => $m->update([
                        'allocated' => $scale($m->allocated),
                        'carried_in' => $scale($m->carried_in),
                    ])
                );
            }

            $household->update(['currency' => $code]);
        });
    }
}
