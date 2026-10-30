<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

function qcHousehold(int $budgets, int $entriesEach): array
{
    $household = Household::factory()->create();
    $household->owner->forceFill(['current_household_id' => $household->id])->save();
    $first = null;

    foreach (range(1, $budgets) as $i) {
        $month = BudgetMonth::factory()
            ->for(Budget::factory()->for($household)->create(['sort' => $i]))
            ->create(['period_start' => '2026-10-01']);
        $first ??= $month->budget_id;

        Transaction::factory()->count($entriesEach)->create([
            'household_id' => $household->id,
            'budget_month_id' => $month->id,
            'user_id' => $household->owner_id,
            'occurred_at' => '2026-10-10 12:00:00',
        ]);
    }

    return [$household, $first];
}

function qcQueries($user, string $url): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->actingAs($user)->get($url)->assertOk();

    return count(DB::getQueryLog());
}

it('runs the same number of queries for 3 budgets and 12 budgets', function (string $path) {
    $this->travelTo('2026-10-29 09:00:00'); // inside the last 5 days, so Close works too

    [$small, $smallBudget] = qcHousehold(3, 5);
    [$big, $bigBudget] = qcHousehold(12, 20);

    $url = fn (Household $h, int $budget) => "/h/{$h->id}".str_replace('{budget}', (string) $budget, $path);

    expect(qcQueries($big->owner, $url($big, $bigBudget)))
        ->toBe(qcQueries($small->owner, $url($small, $smallBudget)));
})->with([
    '',
    '/transactions',
    '/transactions/create',
    '/transfers/create',
    '/report',
    '/budgets',
    '/budgets/{budget}',
    '/close',
    '/family',
]);
