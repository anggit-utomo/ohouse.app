<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Periods;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-27 09:00:00');
    $this->household = Household::factory()->create(['period_start' => '2026-10-27']);
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}";
});

function spBudget(Household $h, string $name, int $allocated, int $spent = 0, bool $repeat = true, int $sort = 0, string $start = '2026-10-27'): BudgetMonth
{
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated, 'repeat_monthly' => $repeat, 'sort' => $sort]);
    $month = BudgetMonth::factory()->for($budget)->create(['period_start' => $start, 'allocated' => $allocated, 'carried_in' => 0]);

    if ($spent) {
        spEntry($month, $spent, "{$start} 12:00:00");
    }

    return $month;
}

function spEntry(BudgetMonth $month, int $amount, string $at): Transaction
{
    return Transaction::factory()->create([
        'household_id' => $month->budget->household_id,
        'budget_month_id' => $month->id,
        'amount' => $amount,
        'occurred_at' => $at,
    ]);
}

function spChoices(array $months, string $choice = 'carry'): array
{
    return collect($months)->mapWithKeys(fn ($m) => [$m->budget_id => $choice])->all();
}

function spNext(BudgetMonth $old, string $start = '2026-11-25'): ?BudgetMonth
{
    return BudgetMonth::where('budget_id', $old->budget_id)->whereDate('period_start', $start)->first();
}

it('finds the current period from the household, or from the open rows when it is not set', function () {
    expect(Periods::current($this->household)->key())->toBe('2026-10-27');

    $other = Household::factory()->create();
    spBudget($other, 'Food', 100_000, 0, true, 0, '2026-10-05');

    expect(Periods::current($other)->key())->toBe('2026-10-05');
});

it('previews what is left as of the chosen payday, ignoring entries after it', function () {
    $food = spBudget($this->household, 'Food', 2_000_000, 1_640_000);
    spEntry($food, 90_000, '2026-11-26 12:00:00');

    $this->actingAs($this->owner)->get("{$this->base}/close?date=2026-11-25")->assertInertia(fn (Assert $p) => $p
        ->component('close/show')->where('startDate', '2026-11-25')->where('canStart', true)
        ->where('rows.0.name', 'Food')->where('rows.0.leftover', 360_000)->where('rows.0.next_allocated', 2_000_000));

    $this->actingAs($this->owner)->get("{$this->base}/close")->assertInertia(fn (Assert $p) => $p
        ->where('startDate', '2026-11-27')->where('rows.0.leftover', 270_000));
});

it('starts the new period with carry, reset and a negative carry', function () {
    $months = [];
    foreach ([['Food', 2_000_000, 1_640_000], ['Internet', 400_000, 400_000], ['Kitchen items', 800_000, 530_000], ['Gasoline', 700_000, 590_000], ['Transport', 600_000, 710_000]] as $i => [$n, $a, $s]) {
        $months[$n] = spBudget($this->household, $n, $a, $s, true, $i);
    }

    $choices = spChoices($months);
    $choices[$months['Gasoline']->budget_id] = 'reset';

    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => $choices])
        ->assertRedirect("{$this->base}/budgets?new=1");

    $total = BudgetMonth::whereDate('period_start', '2026-11-25')->get()->sum(fn ($m) => $m->allocated + $m->carried_in);

    expect($total)->toBe(5_020_000)
        ->and(spNext($months['Transport'])->carried_in)->toBe(-110_000)
        ->and(spNext($months['Gasoline'])->carried_in)->toBe(0)
        ->and($months['Food']->fresh())->closed_at->not->toBeNull()->carry_decision->toBe('carry')
        ->and($months['Food']->fresh()->period_end->toDateString())->toBe('2026-11-24')
        ->and($this->household->fresh()->period_start->toDateString())->toBe('2026-11-25')
        ->and(Periods::current($this->household->fresh())->key())->toBe('2026-11-25');
});

it('moves entries dated on or after the payday into the new period', function () {
    $food = spBudget($this->household, 'Food', 2_000_000, 1_640_000);
    $later = spEntry($food, 90_000, '2026-11-26 12:00:00');

    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => spChoices([$food])])->assertRedirect();

    $next = spNext($food);

    expect($later->fresh()->budget_month_id)->toBe($next->id)
        ->and($next->carried_in)->toBe(360_000)
        ->and(BudgetMonth::with('transactions')->find($next->id)->balance())->toBe(2_270_000);
});

it('ends one-off budgets and asks for no choice about them', function () {
    $food = spBudget($this->household, 'Food', 1_000_000);
    $trip = spBudget($this->household, 'Trip', 500_000, 100_000, false, 1);

    $this->actingAs($this->owner)->get("{$this->base}/close")->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('ending.0', 'Trip'));

    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => spChoices([$food])])->assertRedirect();

    expect($trip->budget->fresh()->archived_at)->not->toBeNull()
        ->and(spNext($trip))->toBeNull()
        ->and($trip->fresh()->closed_at)->not->toBeNull();
});

it('keeps a one-off budget that has entries after the payday', function () {
    $trip = spBudget($this->household, 'Trip', 500_000, 100_000, false);
    $later = spEntry($trip, 40_000, '2026-11-26 12:00:00');

    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => []])->assertRedirect();

    expect(spNext($trip))->allocated->toBe(0)
        ->and($trip->budget->fresh()->archived_at)->toBeNull()
        ->and($later->fresh()->budget_month_id)->toBe(spNext($trip)->id);
});

it('rejects bad dates and missing or unknown choices, and saves nothing', function () {
    $food = spBudget($this->household, 'Food', 1_000_000);
    $ok = spChoices([$food]);

    foreach (['2026-10-27', '2026-10-01', '2026-11-28', 'garbage'] as $start) {
        $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => $start, 'decisions' => $ok])->assertSessionHasErrors('start');
    }

    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => []])->assertSessionHasErrors('decisions');
    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => spChoices([$food], 'maybe')])->assertSessionHasErrors('decisions.'.$food->budget_id);

    expect(BudgetMonth::whereNotNull('closed_at')->count())->toBe(0)->and($this->household->fresh()->period_start->toDateString())->toBe('2026-10-27');
});

it('cannot start the same period twice', function () {
    $food = spBudget($this->household, 'Food', 1_000_000);
    $body = ['start' => '2026-11-25', 'decisions' => spChoices([$food])];

    $this->actingAs($this->owner)->post("{$this->base}/close", $body)->assertRedirect();
    $this->actingAs($this->owner)->post("{$this->base}/close", $body)->assertSessionHasErrors('start');

    expect(BudgetMonth::where('budget_id', $food->budget_id)->count())->toBe(2);
});

it('files new entries in the new period and refuses dates before the payday', function () {
    $food = spBudget($this->household, 'Food', 1_000_000);
    $this->actingAs($this->owner)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => spChoices([$food])]);

    $entry = ['type' => 'expense', 'budget_id' => $food->budget_id, 'amount' => 50_000, 'user_id' => $this->owner->id];

    $this->actingAs($this->owner)->post("{$this->base}/transactions", $entry + ['occurred_at' => '2026-11-27'])->assertRedirect();
    $this->actingAs($this->owner)->post("{$this->base}/transactions", $entry + ['occurred_at' => '2026-11-20'])->assertSessionHasErrors('occurred_at');

    expect(Transaction::where('budget_month_id', spNext($food)->id)->count())->toBe(1);
});

it('shows the reminder only once the period has run 25 days', function () {
    spBudget($this->household, 'Food', 1_000_000);

    foreach (['2026-11-20' => null, '2026-11-21' => 25, '2026-11-27' => 31] as $day => $days) {
        $this->travelTo("{$day} 09:00:00");

        $this->actingAs($this->owner)->get($this->base)->assertInertia(
            fn (Assert $p) => $days === null ? $p->where('banner', null) : $p->where('banner.days', $days)
        );
    }
});

it('saves the budgets of the current period with a repeat choice for each', function () {
    $food = spBudget($this->household, 'Food', 1_000_000);
    $old = spBudget($this->household, 'Old', 100_000, 0, true, 1);

    $this->actingAs($this->owner)->put("{$this->base}/budgets", ['budgets' => [
        ['id' => $food->budget_id, 'name' => 'Food', 'amount' => 2_000_000, 'repeat' => true],
        ['id' => null, 'name' => 'Trip', 'amount' => 500_000, 'repeat' => false],
    ]])->assertSessionHasNoErrors()->assertRedirect();

    $trip = $this->household->budgets()->get()->firstWhere('name', 'Trip');

    expect($food->fresh()->allocated)->toBe(2_000_000)
        ->and($trip->repeat_monthly)->toBeFalse()
        ->and(BudgetMonth::where('budget_id', $trip->id)->first()->period_start->toDateString())->toBe('2026-10-27')
        ->and($old->budget->fresh()->archived_at)->not->toBeNull();
});

it('lets viewers do nothing here and hides the screen from outsiders', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);
    $food = spBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($viewer)->get("{$this->base}/close")->assertForbidden();
    $this->actingAs($viewer)->post("{$this->base}/close", ['start' => '2026-11-25', 'decisions' => spChoices([$food])])->assertForbidden();
    $this->actingAs(User::factory()->create())->get("{$this->base}/close")->assertNotFound();
});
