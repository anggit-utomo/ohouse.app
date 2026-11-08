<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create(['period_start' => '2026-10-01']);
    $this->owner = $this->household->owner;
    $this->url = "/h/{$this->household->id}";
    $this->goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 1_000_000,
    ]);
});

function closedSeptember(Household $household): void
{
    $budget = Budget::factory()->for($household)->create();
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 500_000,
        'closed_at' => now(),
    ]);
}

function budgetMonth(
    Household $household,
    string $name,
    int $allocated,
    string $periodStart = '2026-10-01',
    int $sort = 0
): BudgetMonth {
    $budget = Budget::factory()->for($household)->create([
        'name' => $name,
        'default_amount' => $allocated,
        'sort' => $sort,
    ]);

    return BudgetMonth::factory()->for($budget)->create([
        'period_start' => $periodStart,
        'allocated' => $allocated,
        'carried_in' => 0,
    ]);
}

it('adds money from outside the budget and shows it in the ledger', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 250_000,
        'note' => 'Bonus',
        'occurred_at' => '2026-10-10',
    ])->assertRedirect();

    $movement = GoalMovement::sole();

    expect($movement->amount)->toBe(250_000)
        ->and($movement->note)->toBe('Bonus')
        ->and($movement->household_id)->toBe($this->household->id)
        ->and($movement->user_id)->toBe($this->owner->id)
        ->and($movement->period_start->toDateString())->toBe('2026-10-01');

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goal.balance', 250_000)
            ->where('goal.pct', 25)
            ->where('savings.0.label', 'October')
            ->where('savings.0.saved', 250_000)
            ->has('movements', 1)
            ->where('movements.0.note', 'Bonus')
            ->where('movements.0.user', $this->owner->name)
            ->where('movements.0.signed', 250_000));

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.balance', 250_000)
            ->where('saved', 250_000));
});

it('leaves every budget untouched', function () {
    $budget = Budget::factory()->for($this->household)->create([
        'name' => 'Food',
        'default_amount' => 1_000_000,
    ]);
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => 1_000_000,
        'carried_in' => 0,
    ]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 200_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('budgets.0.left', 1_000_000)
        ->where('budgets.0.spent', 0)
        ->where('budgets.0.pct', 0));
});

it('shows progress beyond the target', function () {
    $this->goal->update(['target_amount' => 100_000]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 150_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goal.balance', 150_000)
            ->where('goal.pct', 150)
            ->where('goal.remaining', 0)
            ->where('goal.over', true));
});

it('lists movements newest first', function () {
    foreach ([['2026-10-05', 100_000], ['2026-10-12', 75_000]] as [$date, $amount]) {
        $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
            'amount' => $amount,
            'occurred_at' => $date,
        ])->assertRedirect();
    }

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('movements.0.amount', 75_000)
            ->where('movements.1.amount', 100_000));
});

it('keeps savings totals for closed periods', function () {
    closedSeptember($this->household);

    GoalMovement::factory()->create([
        'goal_id' => $this->goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'amount' => 300_000,
        'occurred_at' => '2026-09-10 12:00:00',
        'period_start' => '2026-09-01',
    ]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 250_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('savings.0.label', 'October')
            ->where('savings.0.saved', 250_000)
            ->where('savings.1.label', 'September')
            ->where('savings.1.saved', 300_000));
});

it('rejects an amount of zero or less', function () {
    foreach ([0, -50_000] as $amount) {
        $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
            'amount' => $amount,
        ])->assertSessionHasErrors('amount');
    }

    expect(GoalMovement::count())->toBe(0);
});

it('rejects a contribution in a closed period', function () {
    closedSeptember($this->household);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 100_000,
        'occurred_at' => '2026-09-10',
    ])->assertSessionHasErrors([
        'occurred_at' => 'That period is closed. Add it to the current period instead.',
    ]);

    expect(GoalMovement::count())->toBe(0);
});

it('attributes a contribution to its period-start, not its calendar month', function () {
    $this->household->update(['period_start' => '2026-10-27']);
    $this->travelTo('2026-11-08 09:00:00');

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 200_000,
        'occurred_at' => '2026-11-05',
    ])->assertRedirect();

    expect(GoalMovement::sole()->period_start->toDateString())->toBe('2026-10-27');

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('savings.0.period_start', '2026-10-27')
            ->where('savings.0.label', 'October'));
});

it('keeps a movement when its budget month is deleted', function () {
    $budget = Budget::factory()->for($this->household)->create();
    $month = BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => 1_000_000,
    ]);

    $movement = GoalMovement::factory()->create([
        'goal_id' => $this->goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'budget_month_id' => $month->id,
        'amount' => 100_000,
    ]);

    $month->delete();

    expect($movement->fresh()->budget_month_id)->toBeNull()
        ->and($movement->fresh()->amount)->toBe(100_000);

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->has('movements', 1)
            ->where('goal.balance', 100_000));
});

it('rejects a contribution to an archived goal', function () {
    $this->goal->update(['archived_at' => now()]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 100_000,
    ])->assertSessionHasErrors('goal');

    expect(GoalMovement::count())->toBe(0);
});

it('lets viewers read but not add money', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->url}/goals/{$this->goal->id}")->assertOk();

    $this->actingAs($viewer)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 100_000,
    ])->assertForbidden();

    expect(GoalMovement::count())->toBe(0);
});

it('does not add money to another household goal', function () {
    $other = Goal::factory()->for(Household::factory())->create();

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$other->id}/movements", [
        'amount' => 100_000,
    ])->assertNotFound();

    expect(GoalMovement::count())->toBe(0);
});

it('moves money out of a budget without calling it spending', function () {
    $food = budgetMonth($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
    ])->assertRedirect();

    $movement = GoalMovement::sole();

    expect($movement->budget_month_id)->toBe($food->id)
        ->and($movement->period_start->toDateString())->toBe('2026-10-01');

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('budgets.0.left', 700_000)
        ->where('budgets.0.moved', 300_000)
        ->where('budgets.0.spent', 0)
        ->where('budgets.0.available', 1_000_000)
        ->where('budgets.0.pct', 0));

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p->where('goal.balance', 300_000));
});

it('rejects a contribution above what the budget has left', function () {
    $food = budgetMonth($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 1_100_000,
    ])->assertSessionHasErrors([
        'amount' => 'Food only has Rp 1.000.000 left.',
    ]);

    expect(GoalMovement::count())->toBe(0);
});

it('stops offering saved money to a transfer', function () {
    $food = budgetMonth($this->household, 'Food', 1_000_000, sort: 1);
    $gas = budgetMonth($this->household, 'Gas', 500_000, sort: 2);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->url}/budgets/{$food->budget_id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('budgets.0.id', $food->budget_id)
            ->where('budgets.0.left', 700_000));

    $this->actingAs($this->owner)->get("{$this->url}/transactions")
        ->assertInertia(fn (Assert $p) => $p
            ->where('budgets.0.id', $food->budget_id)
            ->where('budgets.0.left', 700_000));

    $this->actingAs($this->owner)->post("{$this->url}/transfers", [
        'from_budget_id' => $food->budget_id,
        'to_budget_id' => $gas->budget_id,
        'amount' => 800_000,
    ])->assertSessionHasErrors('amount');
});

it('attributes a budget contribution to the source period', function () {
    $this->household->update(['period_start' => '2026-10-27']);
    $this->travelTo('2026-11-08 09:00:00');
    $food = budgetMonth($this->household, 'Food', 1_000_000, periodStart: '2026-10-27');

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 200_000,
        'occurred_at' => '2026-11-05',
    ])->assertRedirect();

    expect(GoalMovement::sole()->period_start->toDateString())->toBe('2026-10-27');
});

it('rejects a source budget from another household', function () {
    $other = Household::factory()->create();
    $foreign = Budget::factory()->for($other)->create();

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $foreign->id,
        'amount' => 100_000,
    ])->assertSessionHasErrors('budget_id');

    expect(GoalMovement::count())->toBe(0);
});

it('rejects an archived source budget', function () {
    $food = budgetMonth($this->household, 'Food', 1_000_000);
    $food->budget->update(['archived_at' => now()]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 100_000,
    ])->assertSessionHasErrors('budget_id');

    expect(GoalMovement::count())->toBe(0);
});

it('carries the reduced leftover into the next period', function () {
    $this->travelTo('2026-10-29 09:00:00');
    $food = budgetMonth($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
        'occurred_at' => '2026-10-10',
    ])->assertRedirect();

    $this->actingAs($this->owner)->post("{$this->url}/close", [
        'start' => '2026-10-27',
        'decisions' => [$food->budget_id => 'carry'],
    ])->assertRedirect();

    $next = BudgetMonth::where('budget_id', $food->budget_id)
        ->whereDate('period_start', '2026-10-27')
        ->sole();

    expect($next->carried_in)->toBe(700_000);
});

it('keeps a same-day contribution out of the carry', function () {
    $this->travelTo('2026-10-29 09:00:00');
    $food = budgetMonth($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
        'occurred_at' => '2026-10-29',
    ])->assertRedirect();

    $this->actingAs($this->owner)->post("{$this->url}/close", [
        'start' => '2026-10-29',
        'decisions' => [$food->budget_id => 'carry'],
    ])->assertRedirect();

    $next = BudgetMonth::where('budget_id', $food->budget_id)
        ->whereDate('period_start', '2026-10-29')
        ->sole();

    expect($next->carried_in)->toBe(700_000);
});

it('rejects a budget contribution in a closed period', function () {
    closedSeptember($this->household);
    $september = BudgetMonth::whereDate('period_start', '2026-09-01')->sole();

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'budget_id' => $september->budget_id,
        'amount' => 100_000,
        'occurred_at' => '2026-09-10',
    ])->assertSessionHasErrors('occurred_at');

    expect(GoalMovement::count())->toBe(0);
});
