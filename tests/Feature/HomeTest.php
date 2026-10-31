<?php

use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->url = "/h/{$this->household->id}";
});

function homeBudget(
    Household $h,
    string $name,
    int $allocated,
    int $spent = 0,
    int $carried = 0,
    int $sort = 0
): BudgetMonth {
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated, 'sort' => $sort]);
    $month = BudgetMonth::factory()->for($budget)->create(['period_start' => '2026-10-01', 'allocated' => $allocated, 'carried_in' => $carried]);

    if ($spent) {
        Transaction::factory()->create(['household_id' => $h->id, 'budget_month_id' => $month->id, 'amount' => $spent, 'occurred_at' => '2026-10-10 12:00:00']);
    }

    return $month;
}

it('shows the numbers from the Home screen', function () {
    foreach ([['Food', 2_000_000, 1_640_000], ['Internet', 400_000, 400_000], ['Kitchen items', 800_000, 530_000], ['Gasoline', 700_000, 590_000], ['Transport', 600_000, 710_000]] as $i => [$n, $a, $s]) {
        homeBudget($this->household, $n, $a, $s, 0, $i);
    }

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->component('home')
        ->where('monthLabel', 'October')->where('periodRange', '1 Oct – today')
        ->where('summary.left', 630_000)->where('summary.spent', 3_870_000)->where('summary.available', 4_500_000)->where('summary.pct', 86)
        ->where('budgets.0.name', 'Food')->where('budgets.0.left', 360_000)->where('budgets.0.over', false)
        ->where('budgets.1.left', 0)
        ->where('budgets.4.name', 'Transport')->where('budgets.4.left', -110_000)->where('budgets.4.over', true)->where('budgets.4.pct', 100));
});

it('counts carried-in money and income', function () {
    $month = homeBudget($this->household, 'Food', 1_000_000, 200_000, carried: 100_000);
    Transaction::factory()->type(TransactionTypeEnum::INCOME)->create(['household_id' => $this->household->id, 'budget_month_id' => $month->id, 'amount' => 50_000]);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('budgets.0.left', 950_000)->where('budgets.0.available', 1_150_000));
});

it('hides archived budgets and shows an empty list for a new household', function () {
    homeBudget($this->household, 'Old', 100_000)->budget->update(['archived_at' => now()]);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->has('budgets', 0)->where('summary.left', 0)->where('canEdit', true));
});

it('shows the reminder once the period has run 25 days', function () {
    homeBudget($this->household, 'Food', 1_000_000); // its period started 2026-10-01

    foreach (['2026-10-25' => null, '2026-10-26' => 25] as $day => $days) {
        $this->travelTo("{$day} 09:00:00");

        $this->actingAs($this->owner)->get($this->url)->assertInertia(
            fn (Assert $p) => $days === null ? $p->where('banner', null) : $p->where('banner.days', $days)
        );
    }
});

it('does not show the reminder to viewers', function () {
    homeBudget($this->household, 'Food', 1_000_000);
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);
    $this->travelTo('2026-10-29 09:00:00');

    $this->actingAs($viewer)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('banner', null));
});

it('remembers the household you last opened', function () {
    $this->actingAs($this->owner)->get($this->url)->assertOk();

    expect($this->owner->fresh()->current_household_id)->toBe($this->household->id);
});

it('sends people to their household from the dashboard', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertRedirect($this->url);
});

it('sends people without a household to set one up', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/setup');
});

it('hides the household from outsiders', function () {
    $this->actingAs(User::factory()->create())->get($this->url)->assertNotFound();
});

it('shows one budget with only its own transactions', function () {
    $food = homeBudget($this->household, 'Food', 2_000_000, 85_000);
    homeBudget($this->household, 'Gasoline', 700_000, 50_000);

    $this->actingAs($this->owner)->get("{$this->url}/budgets/{$food->budget_id}")->assertInertia(fn (Assert $p) => $p
        ->component('budgets/show')
        ->where('budget.name', 'Food')->where('summary.left', 1_915_000)->where('summary.spent', 85_000)
        ->has('transactions', 1)->where('transactions.0.signed', -85_000)->where('closed', false));
});

it('opens a past period of a budget and falls back to the open one', function () {
    $food = homeBudget($this->household, 'Food', 2_000_000); // the open period, from 1 Oct
    BudgetMonth::factory()->for($food->budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 1_500_000,
        'closed_at' => now(),
    ]);

    $this->actingAs($this->owner)->get("{$this->url}/budgets/{$food->budget_id}?month=2026-09")
        ->assertInertia(fn (Assert $p) => $p
            ->where('monthLabel', 'September (1 Sep – 30 Sep)')->where('closed', true)->where('summary.available', 1_500_000));

    $this->actingAs($this->owner)->get("{$this->url}/budgets/{$food->budget_id}?month=2027-05")
        ->assertInertia(fn (Assert $p) => $p->where('monthLabel', 'October (1 Oct – today)'));
});

it('does not open another household\'s budget through your url', function () {
    $foreign = homeBudget(Household::factory()->create(), 'Food', 1_000_000);

    $this->actingAs($this->owner)->get("{$this->url}/budgets/{$foreign->budget_id}")->assertNotFound();
});

it('lets viewers see a budget but not the action buttons', function () {
    $food = homeBudget($this->household, 'Food', 1_000_000);
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)
        ->get("{$this->url}/budgets/{$food->budget_id}")
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
});
