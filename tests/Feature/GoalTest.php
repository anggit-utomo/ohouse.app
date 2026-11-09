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
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->url = "/h/{$this->household->id}";
});

it('creates a goal and shows it on Home and the Goals screen', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals", [
        'name' => 'Bali',
        'target_amount' => 6_000_000,
        'target_date' => '2027-06-01',
    ])->assertRedirect();

    $goal = Goal::sole();

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/index')
            ->has('goals', 1)
            ->where('goals.0.name', 'Bali')
            ->where('goals.0.target', 6_000_000)
            ->where('goals.0.balance', 0)
            ->where('goals.0.pct', 0)
            ->where('saved', 0)
            ->where('canEdit', true));

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p
            ->has('goals', 1)
            ->where('goals.0.name', 'Bali')
            ->where('saved', 0));

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/show')
            ->where('goal.name', 'Bali')
            ->where('goal.target_date', '2027-06-01'));
});

it('supplies every active goal ledger from the shared periods', function () {
    $this->household->update(['period_start' => '2026-10-01']);

    $budget = Budget::factory()->for($this->household)->create();
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 500_000,
        'closed_at' => now(),
    ]);

    $goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 1_000_000,
        'target_date' => '2027-06-01',
    ]);

    GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'amount' => 300_000,
        'occurred_at' => '2026-09-10 12:00:00',
        'period_start' => '2026-09-01',
    ]);
    GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'amount' => 200_000,
        'occurred_at' => '2026-10-10 12:00:00',
        'period_start' => '2026-10-01',
    ]);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.target_date', '2027-06-01')
            ->has('goals.0.movements', 2)
            ->where('goals.0.movements.0.amount', 200_000)
            ->where('goals.0.movements.0.can_remove', true)
            ->where('goals.0.movements.1.amount', 300_000)
            ->where('goals.0.movements.1.can_remove', false)
            ->where('goals.0.savings.0.label', 'October')
            ->where('goals.0.savings.0.saved', 200_000)
            ->where('goals.0.savings.1.label', 'September')
            ->where('goals.0.savings.1.saved', 300_000));
});

it('supplies the archived ledger on the goals index', function () {
    $this->household->update(['period_start' => '2026-10-01']);

    $goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 1_000_000,
        'archived_at' => now(),
    ]);

    GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'amount' => 400_000,
        'occurred_at' => '2026-10-10 12:00:00',
        'period_start' => '2026-10-01',
    ]);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->has('archived', 1)
            ->where('archived.0.saved', 400_000)
            ->has('archived.0.movements', 1)
            ->where('archived.0.movements.0.amount', 400_000)
            ->where('archived.0.movements.0.can_remove', false)
            ->where('archived.0.savings.0.label', 'October')
            ->where('archived.0.savings.0.saved', 400_000));
});

it('offers each goal only its own and unclaimed sweep budgets', function () {
    $bali = Goal::factory()->for($this->household)->create(['name' => 'Bali']);
    $car = Goal::factory()->for($this->household)->create(['name' => 'Car', 'sort' => 1]);

    $food = Budget::factory()->for($this->household)->create([
        'name' => 'Food', 'repeat_monthly' => true, 'sort' => 1,
    ]);
    $gas = Budget::factory()->for($this->household)->create([
        'name' => 'Gas', 'repeat_monthly' => true, 'sort' => 2,
    ]);
    $fun = Budget::factory()->for($this->household)->create([
        'name' => 'Fun', 'repeat_monthly' => true, 'sort' => 3,
    ]);
    Budget::factory()->for($this->household)->create([
        'name' => 'Trip', 'repeat_monthly' => false, 'sort' => 4,
    ]);

    $bali->sweepRules()->create(['budget_id' => $food->id]);
    $car->sweepRules()->create(['budget_id' => $gas->id]);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->has('goals.0.sweepBudgets', 2)
            ->where('goals.0.sweepBudgets.0.id', $food->id)
            ->where('goals.0.sweepBudgets.1.id', $fun->id)
            ->where('goals.0.sweepBudgetIds', [$food->id])
            ->has('goals.1.sweepBudgets', 2)
            ->where('goals.1.sweepBudgets.0.id', $gas->id)
            ->where('goals.1.sweepBudgets.1.id', $fun->id)
            ->where('goals.1.sweepBudgetIds', [$gas->id])
            ->has('sweepBudgets', 1)
            ->where('sweepBudgets.0.id', $fun->id));
});

it('requires a name and a target amount', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals", [
        'name' => '',
        'target_amount' => 0,
    ])->assertSessionHasErrors(['name', 'target_amount']);

    expect(Goal::count())->toBe(0);
});

it('renames a goal and changes its target', function () {
    $goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 6_000_000,
    ]);

    $this->actingAs($this->owner)->patch("{$this->url}/goals/{$goal->id}", [
        'name' => 'Japan',
        'target_amount' => 9_000_000,
        'target_date' => '2027-08-01',
        'sort' => 5,
    ])->assertRedirect();

    expect($goal->fresh()->name)->toBe('Japan')
        ->and($goal->fresh()->target_amount)->toBe(9_000_000)
        ->and($goal->fresh()->target_date->toDateString())->toBe('2027-08-01')
        ->and($goal->fresh()->sort)->toBe(5);
});

it('lets an editor create a goal', function () {
    $editor = User::factory()->create();
    $this->household->members()->attach($editor->id, ['role' => 'editor']);

    $this->actingAs($editor)->post("{$this->url}/goals", [
        'name' => 'Laptop',
        'target_amount' => 2_000_000,
    ])->assertRedirect();

    expect(Goal::sole()->name)->toBe('Laptop');
});

it('lists goals in their saved order', function () {
    Goal::factory()->for($this->household)->create(['name' => 'Second', 'sort' => 2]);
    Goal::factory()->for($this->household)->create(['name' => 'First', 'sort' => 1]);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.name', 'First')
            ->where('goals.1.name', 'Second'));
});

it('hides archived goals', function () {
    Goal::factory()->for($this->household)->create(['archived_at' => now()]);

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p->has('goals', 0)->where('saved', 0));

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p->has('goals', 0));
});

it('lets viewers read goals but not change them', function () {
    $goal = Goal::factory()->for($this->household)->create();
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->url}/goals")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
    $this->actingAs($viewer)->get("{$this->url}/goals/{$goal->id}")->assertOk();

    $this->actingAs($viewer)->post("{$this->url}/goals", [
        'name' => 'Nope',
        'target_amount' => 1_000,
    ])->assertForbidden();

    $this->actingAs($viewer)->patch("{$this->url}/goals/{$goal->id}", [
        'name' => 'Nope',
        'target_amount' => 1_000,
    ])->assertForbidden();
});

it('hides goals from outsiders', function () {
    $goal = Goal::factory()->for($this->household)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("{$this->url}/goals")->assertNotFound();
    $this->actingAs($outsider)->get("{$this->url}/goals/{$goal->id}")->assertNotFound();
});

it('does not open a goal from another household', function () {
    $goal = Goal::factory()->for(Household::factory())->create();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$goal->id}")->assertNotFound();
});
