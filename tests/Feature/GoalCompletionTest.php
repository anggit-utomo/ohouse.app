<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\GoalSweepRule;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\Route;
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

function completionSaved(Goal $goal, Household $household, User $user, int $amount): GoalMovement
{
    return GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $household->id,
        'user_id' => $user->id,
        'type' => 'contribution',
        'amount' => $amount,
        'occurred_at' => '2026-10-10 12:00:00',
        'period_start' => '2026-10-01',
    ]);
}

function completionBudget(Household $household, string $name, int $allocated, int $sort = 0): BudgetMonth
{
    $budget = Budget::factory()->for($household)->create([
        'name' => $name,
        'default_amount' => $allocated,
        'sort' => $sort,
    ]);

    return BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => $allocated,
        'carried_in' => 0,
    ]);
}

it('empties a goal through one final withdrawal outside the budget and archives it', function () {
    completionSaved($this->goal, $this->household, $this->owner, 600_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $goal = $this->goal->fresh();
    $withdrawal = GoalMovement::where('type', 'withdrawal')->sole();

    expect($goal->archived_at)->not->toBeNull()
        ->and($withdrawal->amount)->toBe(600_000)
        ->and($withdrawal->budget_month_id)->toBeNull()
        ->and($withdrawal->user_id)->toBe($this->owner->id)
        ->and($withdrawal->period_start->toDateString())->toBe('2026-10-01')
        ->and($goal->balance())->toBe(0);

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('archived', true)
            ->where('goal.balance', 0)
            ->has('movements', 2));
});

it('returns the full balance to a budget in the open period', function () {
    completionSaved($this->goal, $this->household, $this->owner, 600_000);
    $food = completionBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/complete", [
        'budget_id' => $food->budget_id,
        'note' => 'Holiday leftovers',
    ])->assertRedirect();

    $withdrawal = GoalMovement::where('type', 'withdrawal')->sole();

    expect($withdrawal->budget_month_id)->toBe($food->id)
        ->and($withdrawal->note)->toBe('Holiday leftovers')
        ->and($food->fresh()->balance())->toBe(1_600_000)
        ->and($food->fresh()->spent())->toBe(0);
});

it('archives an empty goal without creating a movement', function () {
    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    expect($this->goal->fresh()->archived_at)->not->toBeNull()
        ->and(GoalMovement::count())->toBe(0);
});

it('keeps completion the only path to archive and refuses edits once archived', function () {
    completionSaved($this->goal, $this->household, $this->owner, 600_000);

    $this->actingAs($this->owner)->patch("{$this->url}/goals/{$this->goal->id}", [
        'name' => 'Japan',
        'target_amount' => 2_000_000,
        'archived_at' => '2026-10-15 09:00:00',
    ])->assertRedirect();

    expect($this->goal->fresh()->archived_at)->toBeNull()
        ->and($this->goal->fresh()->name)->toBe('Japan');

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    expect($this->goal->fresh()->archived_at)->not->toBeNull();

    $this->actingAs($this->owner)->patch("{$this->url}/goals/{$this->goal->id}", [
        'name' => 'Nope',
        'target_amount' => 3_000_000,
    ])->assertForbidden();

    expect($this->goal->fresh()->name)->toBe('Japan');
});

it('flips the completed goal into the archived index ledger without actions', function () {
    completionSaved($this->goal, $this->household, $this->owner, 600_000);
    $food = completionBudget($this->household, 'Food', 1_000_000);
    $this->goal->sweepRules()->create(['budget_id' => $food->budget_id]);
    $index = "{$this->url}/goals";

    $this->actingAs($this->owner)->from($index)
        ->post("{$this->url}/goals/{$this->goal->id}/complete", [
            'budget_id' => $food->budget_id,
        ])->assertRedirect($index);

    $this->actingAs($this->owner)->get($index)
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/index')
            ->has('goals', 0)
            ->has('archived', 1)
            ->where('archived.0.id', $this->goal->id)
            ->where('archived.0.archived_at', '2026-10-15')
            ->where('archived.0.balance', 0)
            ->where('archived.0.saved', 600_000)
            ->where('archived.0.target', 1_000_000)
            ->where('archived.0.sweepBudgetIds', [])
            ->has('archived.0.movements', 2)
            ->where('archived.0.movements.0.type', 'withdrawal')
            ->where('archived.0.movements.0.amount', 600_000)
            ->where('archived.0.movements.0.can_remove', false)
            ->where('archived.0.movements.1.type', 'contribution')
            ->where('archived.0.movements.1.can_remove', false)
            ->where('saved', 0)
            ->has('budgets', 1)
            ->where('budgets.0.left', 1_600_000));

    expect(GoalSweepRule::count())->toBe(0);
});

it('flips an empty completed goal into archived history without a movement', function () {
    $index = "{$this->url}/goals";

    $this->actingAs($this->owner)->from($index)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect($index);

    $this->actingAs($this->owner)->get($index)
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/index')
            ->has('goals', 0)
            ->has('archived', 1)
            ->where('archived.0.id', $this->goal->id)
            ->where('archived.0.balance', 0)
            ->has('archived.0.movements', 0)
            ->where('saved', 0));

    expect(GoalMovement::count())->toBe(0);
});

it('lists archived goals in a separate history section with final figures', function () {
    completionSaved($this->goal, $this->household, $this->owner, 600_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $laptop = Goal::factory()->for($this->household)->create([
        'name' => 'Laptop',
        'target_amount' => 2_000_000,
        'sort' => 1,
    ]);
    completionSaved($laptop, $this->household, $this->owner, 200_000);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->has('goals', 1)
            ->where('goals.0.name', 'Laptop')
            ->where('saved', 200_000)
            ->has('archived', 1)
            ->where('archived.0.name', 'Bali')
            ->where('archived.0.saved', 600_000)
            ->where('archived.0.target', 1_000_000)
            ->where('archived.0.archived_at', '2026-10-15'));

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p
            ->has('goals', 1)
            ->where('goals.0.name', 'Laptop')
            ->where('saved', 200_000));
});

it('renders an archived goal read-only and accepts no movements or undo', function () {
    completionSaved($this->goal, $this->household, $this->owner, 400_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('archived', true)
            ->where('canEdit', false)
            ->has('movements', 2)
            ->where('movements.0.can_remove', false)
            ->where('movements.1.can_remove', false));

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'contribution',
        'amount' => 50_000,
    ])->assertSessionHasErrors('goal');

    $withdrawal = GoalMovement::where('type', 'withdrawal')->sole();

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$withdrawal->id}")
        ->assertSessionHasErrors('goal');

    expect(GoalMovement::count())->toBe(2);
});

it('never deletes a goal and keeps its ledger after archive', function () {
    $contribution = completionSaved($this->goal, $this->household, $this->owner, 500_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    expect(Route::has('goals.destroy'))->toBeFalse()
        ->and(Goal::count())->toBe(1)
        ->and($contribution->fresh())->not->toBeNull();

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}")
        ->assertMethodNotAllowed();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('archived', true)
            ->has('movements', 2));
});

it('lets a viewer read archived history but not complete a goal', function () {
    completionSaved($this->goal, $this->household, $this->owner, 300_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $active = Goal::factory()->for($this->household)->create([
        'name' => 'Laptop',
        'target_amount' => 2_000_000,
    ]);

    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->url}/goals")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('archived', 1));
    $this->actingAs($viewer)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('archived', true));

    $this->actingAs($viewer)
        ->post("{$this->url}/goals/{$active->id}/complete")
        ->assertForbidden();

    expect($active->fresh()->archived_at)->toBeNull();
});

it('hides archived goals from an outsider', function () {
    completionSaved($this->goal, $this->household, $this->owner, 300_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("{$this->url}/goals")->assertNotFound();
    $this->actingAs($outsider)->get("{$this->url}/goals/{$this->goal->id}")->assertNotFound();
    $this->actingAs($outsider)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertNotFound();
});

it('rejects completing an archived goal again without a second withdrawal', function () {
    completionSaved($this->goal, $this->household, $this->owner, 300_000);

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->post("{$this->url}/goals/{$this->goal->id}/complete")
        ->assertSessionHasErrors('goal');

    expect(GoalMovement::where('type', 'withdrawal')->count())->toBe(1);
});
