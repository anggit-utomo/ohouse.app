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

function savedMoney(Goal $goal, Household $household, User $user, int $amount, ?int $budgetMonthId = null): GoalMovement
{
    return GoalMovement::factory()->create([
        'goal_id' => $goal->id,
        'household_id' => $household->id,
        'user_id' => $user->id,
        'budget_month_id' => $budgetMonthId,
        'amount' => $amount,
        'occurred_at' => '2026-10-10 12:00:00',
        'period_start' => '2026-10-01',
    ]);
}

function withdrawalBudget(
    Household $household,
    string $name,
    int $allocated,
    int $sort = 0,
    string $periodStart = '2026-10-01'
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

function closedWithdrawalMonth(Household $household): void
{
    $budget = Budget::factory()->for($household)->create();
    BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 500_000,
        'closed_at' => now(),
    ]);
}

it('takes money out to outside the budget', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 200_000,
        'note' => 'Deposit',
    ])->assertRedirect();

    $withdrawal = GoalMovement::where('type', 'withdrawal')->sole();

    expect($withdrawal->amount)->toBe(200_000)
        ->and($withdrawal->note)->toBe('Deposit')
        ->and($withdrawal->budget_month_id)->toBeNull();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goal.balance', 300_000)
            ->where('movements.0.type', 'withdrawal')
            ->where('movements.0.note', 'Deposit')
            ->where('movements.0.signed', -200_000)
            ->where('movements.0.user', $this->owner->name)
            ->where('movements.0.can_remove', true));

    withdrawalBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->get("{$this->url}/report")
        ->assertInertia(fn (Assert $p) => $p
            ->where('spent', 0)
            ->where('hasData', false));
});

it('returns money to a budget without calling it income', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);
    $food = withdrawalBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'budget_id' => $food->budget_id,
        'amount' => 200_000,
    ])->assertRedirect();

    expect($food->fresh()->balance())->toBe(1_200_000)
        ->and($food->fresh()->spent())->toBe(0);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('budgets.0.left', 1_200_000)
        ->where('budgets.0.spent', 0)
        ->where('budgets.0.pct', 0));

    $this->actingAs($this->owner)->get("{$this->url}/report")
        ->assertInertia(fn (Assert $p) => $p
            ->where('spent', 0)
            ->where('hasData', false));
});

it('rejects a withdrawal above what is saved', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 600_000,
    ])->assertSessionHasErrors([
        'amount' => 'Only Rp 500.000 is saved.',
    ]);

    expect(GoalMovement::where('type', 'withdrawal')->count())->toBe(0);
});

it('accepts a withdrawal for exactly what is saved', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 500_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$this->goal->id}")
        ->assertInertia(fn (Assert $p) => $p->where('goal.balance', 0));
});

it('rejects a withdrawal in a closed period', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);
    closedWithdrawalMonth($this->household);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 100_000,
        'occurred_at' => '2026-09-10',
    ])->assertSessionHasErrors([
        'occurred_at' => 'That period is closed. Add it to the current period instead.',
    ]);

    expect(GoalMovement::where('type', 'withdrawal')->count())->toBe(0);
});

it('rejects a withdrawal into a closed budget month', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);
    $food = withdrawalBudget($this->household, 'Food', 1_000_000);
    $food->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'budget_id' => $food->budget_id,
        'amount' => 100_000,
    ])->assertSessionHasErrors('occurred_at');

    expect(GoalMovement::where('type', 'withdrawal')->count())->toBe(0);
});

it('removes a contribution and restores the source budget', function () {
    $food = withdrawalBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'contribution',
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
    ])->assertRedirect();

    $movement = GoalMovement::where('type', 'contribution')->sole();

    expect($food->fresh()->balance())->toBe(700_000);

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$movement->id}")
        ->assertRedirect();

    expect(GoalMovement::count())->toBe(0)
        ->and($food->fresh()->balance())->toBe(1_000_000);
});

it('removes a withdrawal and restores both balances', function () {
    savedMoney($this->goal, $this->household, $this->owner, 500_000);
    $food = withdrawalBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'budget_id' => $food->budget_id,
        'amount' => 200_000,
    ])->assertRedirect();

    $withdrawal = GoalMovement::where('type', 'withdrawal')->sole();

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$withdrawal->id}")
        ->assertRedirect();

    expect($this->goal->fresh()->balance())->toBe(500_000)
        ->and($food->fresh()->balance())->toBe(1_000_000);
});

it('refuses to remove a contribution that would take the goal below zero', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'contribution',
        'amount' => 300_000,
    ])->assertRedirect();

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 250_000,
    ])->assertRedirect();

    $contribution = GoalMovement::where('type', 'contribution')->sole();

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$contribution->id}")
        ->assertSessionHasErrors('movement');

    expect(GoalMovement::count())->toBe(2);
});

it('refuses to remove a movement from a closed period', function () {
    $movement = GoalMovement::factory()->create([
        'goal_id' => $this->goal->id,
        'household_id' => $this->household->id,
        'user_id' => $this->owner->id,
        'amount' => 100_000,
        'occurred_at' => '2026-09-10 12:00:00',
        'period_start' => '2026-09-01',
    ]);

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$movement->id}")
        ->assertSessionHasErrors([
            'movement' => 'That period is closed. Add a correction in the current period instead.',
        ]);

    expect(GoalMovement::count())->toBe(1);
});

it('lets viewers read but not withdraw or remove', function () {
    $movement = savedMoney($this->goal, $this->household, $this->owner, 500_000);
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->url}/goals/{$this->goal->id}")->assertOk();

    $this->actingAs($viewer)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 100_000,
    ])->assertForbidden();

    $this->actingAs($viewer)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$movement->id}")
        ->assertForbidden();
});

it('does not remove another goal movement', function () {
    $otherGoal = Goal::factory()->for(Household::factory())->create();
    $movement = GoalMovement::factory()->create([
        'goal_id' => $otherGoal->id,
        'household_id' => $otherGoal->household_id,
        'user_id' => $otherGoal->household->owner_id,
    ]);

    $this->actingAs($this->owner)
        ->delete("{$this->url}/goals/{$this->goal->id}/movements/{$movement->id}")
        ->assertNotFound();

    expect(GoalMovement::count())->toBe(1);
});

it('carries net goal money into the next period', function () {
    $this->travelTo('2026-10-29 09:00:00');
    $food = withdrawalBudget($this->household, 'Food', 1_000_000);

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'contribution',
        'budget_id' => $food->budget_id,
        'amount' => 300_000,
        'occurred_at' => '2026-10-10',
    ])->assertRedirect();

    $this->actingAs($this->owner)->post("{$this->url}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'budget_id' => $food->budget_id,
        'amount' => 200_000,
        'occurred_at' => '2026-10-12',
    ])->assertRedirect();

    $this->actingAs($this->owner)->post("{$this->url}/close", [
        'start' => '2026-10-27',
        'decisions' => [$food->budget_id => 'carry'],
    ])->assertRedirect();

    $next = BudgetMonth::where('budget_id', $food->budget_id)
        ->whereDate('period_start', '2026-10-27')
        ->sole();

    expect($next->carried_in)->toBe(900_000);
});
