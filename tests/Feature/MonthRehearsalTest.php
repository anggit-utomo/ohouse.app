<?php

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('runs a whole month the way you and your wife will', function () {
    // 1 Oct: sign up, create the household, set the budgets
    $this->travelTo('2026-10-01 09:00:00');
    $owner = User::factory()->create();
    $wife = User::factory()->create();

    $this->actingAs($owner)
        ->post(
            '/setup',
            ['name' => 'Our home', 'budgets' => ['Food', 'Gasoline', 'Transport']]
        )->assertRedirect();

    $household = Household::first();
    $base = "/h/{$household->id}";
    $id = $household->budgets()->get()->pluck('id', 'name');

    $this->actingAs($owner)->put("{$base}/budgets", [
        'period' => '2026-10',
        'repeat' => true,
        'budgets' => [
            ['id' => $id['Food'], 'name' => 'Food', 'amount' => 2_000_000],
            ['id' => $id['Gasoline'], 'name' => 'Gasoline', 'amount' => 700_000],
            ['id' => $id['Transport'], 'name' => 'Transport', 'amount' => 600_000],
        ],
    ])->assertRedirect();

    // your wife joins with the pair code
    $this->actingAs($owner)->post("{$base}/family/invite")->assertRedirect();
    $this->actingAs($wife)->post('/join', ['code' => HouseholdInvite::first()->code])->assertRedirect($base);

    // the month's spending
    $spend = function (
        string $day,
        User $by,
        string $type,
        string $budget,
        int $amount,
        array $extra = []
    ) use ($base, $id) {
        $this->travelTo("{$day} 10:00:00");
        $this->actingAs($by)->post("{$base}/transactions", array_merge([
            'type' => $type,
            'budget_id' => $id[$budget],
            'amount' => $amount,
            'note' => "{$type} {$budget}",
            'occurred_at' => $day,
            'user_id' => $by->id,
        ], $extra))->assertRedirect();
    };

    $spend('2026-10-05', $owner, 'expense', 'Food', 1_640_000);
    $spend('2026-10-08', $wife, 'expense', 'Gasoline', 590_000);
    $spend('2026-10-12', $owner, 'expense', 'Transport', 710_000); // over budget by 110.000
    $spend('2026-10-20', $wife, 'income', 'Food', 100_000);

    $this->travelTo('2026-10-22 10:00:00');
    $this->actingAs($owner)
        ->post(
            "{$base}/transfers",
            ['from_budget_id' => $id['Gasoline'], 'to_budget_id' => $id['Transport'], 'amount' => 100_000]
        )->assertRedirect();

    // 29 Oct: the banner shows, but October cannot be closed yet
    $this->travelTo('2026-10-29 09:00:00');
    $decisions = [$id['Food'] => 'carry', $id['Gasoline'] => 'reset', $id['Transport'] => 'carry'];

    $this->actingAs($owner)->get($base)->assertInertia(fn (Assert $p) => $p->where('banner.daysLeft', 2)->where('banner.overdue', false));
    $this->actingAs($owner)->post("{$base}/close", ['period' => '2026-10', 'decisions' => $decisions])->assertSessionHasErrors('period');

    // 1 Nov: October is over, close it
    $this->travelTo('2026-11-01 08:00:00');

    $this->actingAs($owner)->get($base)->assertInertia(fn (Assert $p) => $p->where('banner.overdue', true)->where('banner.label', 'October'));
    $this->actingAs($owner)->post("{$base}/close", ['period' => '2026-10', 'decisions' => $decisions])->assertRedirect($base);

    $this->actingAs($wife)->get($base)->assertInertia(fn (Assert $p) => $p
        ->where('banner', null)
        ->where('budgets.0.name', 'Food')->where('budgets.0.left', 2_460_000)
        ->where('budgets.1.name', 'Gasoline')->where('budgets.1.left', 700_000)
        ->where('budgets.2.name', 'Transport')->where('budgets.2.left', 590_000)
        ->where('summary.left', 3_750_000));

    // October is locked, but a correction in November works
    $food = Transaction::where('type', 'expense')->get()->first(fn ($t) => $t->amount === 1_640_000);

    $this->actingAs($owner)->put("{$base}/transactions/{$food->id}", [
        'type' => 'expense',
        'budget_id' => $id['Food'],
        'amount' => 1,
        'occurred_at' => '2026-10-05',
        'user_id' => $owner->id,
    ])->assertSessionHasErrors('occurred_at');

    $this->actingAs($owner)->post("{$base}/transactions", [
        'type' => 'income',
        'budget_id' => $id['Food'],
        'amount' => 40_000,
        'note' => 'Correction',
        'occurred_at' => '2026-11-01',
        'user_id' => $owner->id,
        'corrects_transaction_id' => $food->id,
    ])->assertRedirect();

    $this->actingAs($owner)->get($base)->assertInertia(fn (Assert $p) => $p->where('budgets.0.left', 2_500_000));

    // the report and history for October did not move
    $this->actingAs($owner)->get("{$base}/report?month=2026-10")->assertInertia(fn (Assert $p) => $p
        ->where('spent', 2_940_000)->where('income', 100_000)
        ->where('budgets.0.name', 'Food')->where('budgets.0.pct', 56)
        ->where('budgets.1.name', 'Transport')->where('budgets.1.pct', 24)
        ->where('budgets.2.name', 'Gasoline')->where('budgets.2.pct', 20)
        ->where('people.0.id', $owner->id)->where('people.0.spent', 2_350_000)->where('people.1.spent', 590_000));

    $this->actingAs($wife)
        ->get("{$base}/transactions?month=2026-10")
        ->assertInertia(fn (Assert $p) => $p->has('transactions', 5)); // the move is one row

    // the daily command does not duplicate anything
    $this->travelTo('2026-11-02 00:05:00');
    $this->artisan('budgets:prepare-month')->assertSuccessful();

    expect(BudgetMonth::whereDate('period_start', '2026-11-01')->count())->toBe(3)
        ->and(BudgetMonth::whereDate('period_start', '2026-10-01')->whereNull('closed_at')->count())->toBe(0);
});
