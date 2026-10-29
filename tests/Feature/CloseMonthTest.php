<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-02 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->url = "/h/{$this->household->id}/close";
});

function closeBudget(Household $h, string $name, int $allocated, int $spent = 0, string $period = '2026-10-01', int $sort = 0): BudgetMonth
{
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated, 'sort' => $sort]);
    $month = BudgetMonth::factory()->for($budget)->create(['period_start' => $period, 'allocated' => $allocated, 'carried_in' => 0]);

    if ($spent) {
        Transaction::factory()->create(['household_id' => $h->id, 'budget_month_id' => $month->id, 'amount' => $spent, 'occurred_at' => "{$period} 12:00:00"]);
    }

    return $month;
}

function closeScenario(Household $h): array
{
    $ids = [];

    foreach ([['Food', 2_000_000, 1_640_000], ['Internet', 400_000, 400_000], ['Kitchen items', 800_000, 530_000], ['Gasoline', 700_000, 590_000], ['Transport', 600_000, 710_000]] as $i => [$n, $a, $s]) {
        $ids[$n] = closeBudget($h, $n, $a, $s, sort: $i)->budget_id;
    }

    return $ids;
}

it('lists what is left per budget for the month to close', function () {
    closeScenario($this->household);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->component('close/show')
        ->where('periodLabel', 'October')->where('nextLabel', 'November')->where('closesOn', '1 November')->where('canClose', true)
        ->where('rows.0.name', 'Food')->where('rows.0.leftover', 360_000)->where('rows.0.next_allocated', 2_000_000)
        ->where('rows.1.leftover', 0)
        ->where('rows.4.name', 'Transport')->where('rows.4.leftover', -110_000));
});

it('closes the month with the choices made and reaches the November total from the screen', function () {
    $ids = closeScenario($this->household);

    $this->actingAs($this->owner)->post($this->url, [
        'period' => '2026-10',
        'decisions' => [$ids['Food'] => 'carry', $ids['Internet'] => 'carry', $ids['Kitchen items'] => 'carry', $ids['Gasoline'] => 'reset', $ids['Transport'] => 'carry'],
    ])->assertRedirect("/h/{$this->household->id}");

    $total = BudgetMonth::whereDate('period_start', '2026-11-01')->get()->sum(fn ($m) => $m->allocated + $m->carried_in);

    expect($total)->toBe(5_020_000)
        ->and(BudgetMonth::whereDate('period_start', '2026-10-01')->whereNull('closed_at')->count())->toBe(0);

    $this->actingAs($this->owner)->get("/h/{$this->household->id}")->assertInertia(fn (Assert $p) => $p->where('banner', null));
});

it('goes back to Home when there is nothing to close', function () {
    $this->travelTo('2026-10-15 09:00:00');
    closeScenario($this->household);

    $this->actingAs($this->owner)->get($this->url)->assertRedirect("/h/{$this->household->id}");
});

it('previews in the last 5 days but refuses to close before the month is over', function () {
    $this->travelTo('2026-10-29 09:00:00');
    $ids = closeScenario($this->household);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('canClose', false)->where('closesOn', '1 November'));

    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-10', 'decisions' => collect($ids)->map(fn () => 'carry')->mapWithKeys(fn ($v, $k) => [$ids[$k] => $v])->all()])
        ->assertSessionHasErrors('period');

    expect(BudgetMonth::whereNotNull('closed_at')->count())->toBe(0);
});

it('needs a decision for every budget and saves nothing otherwise', function () {
    $ids = closeScenario($this->household);

    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-10', 'decisions' => [$ids['Food'] => 'carry']])
        ->assertSessionHasErrors('decisions');

    expect(BudgetMonth::whereNotNull('closed_at')->count())->toBe(0);
});

it('rejects unknown choices and a month that is not the pending one', function () {
    $ids = closeScenario($this->household);
    $all = fn (string $c) => collect($ids)->mapWithKeys(fn ($id) => [$id => $c])->all();

    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-10', 'decisions' => $all('maybe')])->assertSessionHasErrors('decisions.'.reset($ids));
    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-09', 'decisions' => $all('carry')])->assertSessionHasErrors('period');
    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-12', 'decisions' => $all('carry')])->assertSessionHasErrors('period');
});

it('cannot be submitted twice', function () {
    $ids = closeScenario($this->household);
    $all = collect($ids)->mapWithKeys(fn ($id) => [$id => 'carry'])->all();

    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-10', 'decisions' => $all])->assertRedirect();
    $this->actingAs($this->owner)->post($this->url, ['period' => '2026-10', 'decisions' => $all])->assertSessionHasErrors('period');

    expect(BudgetMonth::whereDate('period_start', '2026-11-01')->count())->toBe(5);
});

it('offers the oldest unclosed month first', function () {
    closeBudget($this->household, 'Food', 1_000_000, 100_000, '2026-09-01');
    closeBudget($this->household, 'Gas', 500_000, 0, '2026-10-01');

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('periodLabel', 'September')->where('nextLabel', 'October'));
});

it('shows an overdue banner on Home for a finished month that is still open', function () {
    closeScenario($this->household);

    $this->actingAs($this->owner)->get("/h/{$this->household->id}")->assertInertia(fn (Assert $p) => $p
        ->where('banner.label', 'October')->where('banner.overdue', true)->where('banner.daysLeft', null));
});

it('ignores archived budgets', function () {
    $month = closeBudget($this->household, 'Old', 100_000);
    $month->budget->update(['archived_at' => now()]);

    $this->actingAs($this->owner)->get($this->url)->assertRedirect("/h/{$this->household->id}");
});

it('does not touch another household', function () {
    $other = Household::factory()->create();
    $theirs = closeBudget($other, 'Food', 1_000_000, 100_000);
    $ids = closeScenario($this->household);

    $this->actingAs($this->owner)->post($this->url, [
        'period' => '2026-10',
        'decisions' => collect($ids)->mapWithKeys(fn ($id) => [$id => 'carry'])->all() + [$theirs->budget_id => 'reset'],
    ])->assertRedirect();

    expect($theirs->fresh()->closed_at)->toBeNull();
});

it('lets viewers do nothing here and hides the screen from outsiders', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);
    closeScenario($this->household);

    $this->actingAs($viewer)->get($this->url)->assertForbidden();
    $this->actingAs($viewer)->post($this->url, ['period' => '2026-10', 'decisions' => []])->assertForbidden();
    $this->actingAs(User::factory()->create())->get($this->url)->assertNotFound();
});
