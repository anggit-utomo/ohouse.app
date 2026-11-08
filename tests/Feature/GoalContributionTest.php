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
    $budget = Budget::factory()->for($this->household)->create();
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 500_000,
        'closed_at' => now(),
    ]);

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
    $budget = Budget::factory()->for($this->household)->create();
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 500_000,
        'closed_at' => now(),
    ]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'amount' => 100_000,
        'occurred_at' => '2026-09-10',
    ])->assertSessionHasErrors('occurred_at');

    expect(GoalMovement::count())->toBe(0);
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
