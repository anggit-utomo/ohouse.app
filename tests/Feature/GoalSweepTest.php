<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Goal;
use App\Models\GoalMovement;
use App\Models\GoalSweepRule;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-27 09:00:00');
    $this->household = Household::factory()->create(['period_start' => '2026-10-27']);
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}";
    $this->goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 1_000_000,
    ]);
});

function sweepBudget(
    Household $household,
    string $name,
    int $allocated,
    int $spent = 0,
    bool $repeat = true,
    int $sort = 0
): BudgetMonth {
    $budget = Budget::factory()->for($household)->create([
        'name' => $name,
        'default_amount' => $allocated,
        'repeat_monthly' => $repeat,
        'sort' => $sort,
    ]);

    $month = BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-27',
        'allocated' => $allocated,
        'carried_in' => 0,
    ]);

    if ($spent !== 0) {
        Transaction::factory()->create([
            'household_id' => $household->id,
            'budget_month_id' => $month->id,
            'amount' => $spent,
            'occurred_at' => '2026-10-27 12:00:00',
        ]);
    }

    return $month;
}

function sweepRule(Goal $goal, BudgetMonth $month): GoalSweepRule
{
    return $goal->sweepRules()->create(['budget_id' => $month->budget_id]);
}

function sweepChoices(array $months, string $choice = 'carry'): array
{
    return collect($months)->mapWithKeys(fn ($month) => [$month->budget_id => $choice])->all();
}

function sweepNext(BudgetMonth $old, string $start = '2026-11-25'): ?BudgetMonth
{
    return BudgetMonth::where('budget_id', $old->budget_id)
        ->whereDate('period_start', $start)
        ->first();
}

it('previews and sweeps a budget leftover into the goal before carrying the remainder', function () {
    $food = sweepBudget($this->household, 'Food', 2_000_000, 1_640_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)->get("{$this->base}/close?date=2026-11-25")
        ->assertInertia(fn (Assert $p) => $p
            ->where('rows.0.leftover', 360_000)
            ->where('rows.0.sweep_goal', 'Bali')
            ->where('rows.0.sweep_amount', 360_000));

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => sweepChoices([$food]),
    ])->assertRedirect();

    $movement = GoalMovement::where('type', 'contribution')->sole();

    expect($movement->amount)->toBe(360_000)
        ->and($movement->budget_month_id)->toBe($food->id)
        ->and($movement->period_start->toDateString())->toBe('2026-10-27')
        ->and($movement->occurred_at->toDateString())->toBe('2026-10-27')
        ->and(sweepNext($food)->carried_in)->toBe(0)
        ->and($this->goal->fresh()->balance())->toBe(360_000);
});

it('resets only the remainder after the sweep', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000, 200_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => sweepChoices([$food], 'reset'),
    ])->assertRedirect();

    expect(GoalMovement::where('type', 'contribution')->sole()->amount)->toBe(800_000)
        ->and(sweepNext($food)->carried_in)->toBe(0)
        ->and($this->goal->fresh()->balance())->toBe(800_000);
});

it('moves nothing when the leftover is zero or negative', function () {
    foreach ([[500_000, 500_000], [500_000, 600_000]] as [$allocated, $spent]) {
        $household = Household::factory()->create(['period_start' => '2026-10-27']);
        $goal = Goal::factory()->for($household)->create();
        $food = sweepBudget($household, 'Food', $allocated, $spent);
        sweepRule($goal, $food);

        $this->actingAs($household->owner)->post("/h/{$household->id}/close", [
            'start' => '2026-11-25',
            'decisions' => sweepChoices([$food]),
        ])->assertRedirect();
    }

    expect(GoalMovement::count())->toBe(0);
});

it('ignores a sweep rule on a one-off budget', function () {
    $trip = sweepBudget($this->household, 'Trip', 500_000, 100_000, repeat: false);
    sweepRule($this->goal, $trip);

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => [],
    ])->assertRedirect();

    expect(GoalMovement::count())->toBe(0)
        ->and($trip->budget->fresh()->archived_at)->not->toBeNull();
});

it('prunes rules when saving budgets makes them one-off or archived', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000);
    $old = sweepBudget($this->household, 'Old', 100_000, sort: 1);
    sweepRule($this->goal, $food);
    sweepRule($this->goal, $old);

    $this->actingAs($this->owner)->put("{$this->base}/budgets", ['budgets' => [
        ['id' => $food->budget_id, 'name' => 'Food', 'amount' => 1_000_000, 'repeat' => false],
    ]])->assertSessionHasNoErrors();

    expect($food->budget->fresh()->repeat_monthly)->toBeFalse()
        ->and($old->budget->fresh()->archived_at)->not->toBeNull()
        ->and(GoalSweepRule::count())->toBe(0);
});

it('deletes sweep rules when the goal is archived', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)
        ->post("{$this->base}/goals/{$this->goal->id}/complete")
        ->assertRedirect();

    expect($this->goal->fresh()->archived_at)->not->toBeNull()
        ->and(GoalSweepRule::count())->toBe(0);
});

it('locks a sweep and allows recovery only by a withdrawal in the new period', function () {
    $food = sweepBudget($this->household, 'Food', 2_000_000, 1_640_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => sweepChoices([$food]),
    ])->assertRedirect();

    $sweep = GoalMovement::sole();

    $this->actingAs($this->owner)
        ->delete("{$this->base}/goals/{$this->goal->id}/movements/{$sweep->id}")
        ->assertSessionHasErrors('movement');

    expect(GoalMovement::count())->toBe(1);

    $this->actingAs($this->owner)->get("{$this->base}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.movements.0.can_remove', false)
            ->where('goals.0.savings.0.period_start', '2026-10-27')
            ->where('goals.0.savings.0.saved', 360_000));

    $this->actingAs($this->owner)->post("{$this->base}/goals/{$this->goal->id}/movements", [
        'type' => 'withdrawal',
        'amount' => 100_000,
        'occurred_at' => '2026-11-27',
    ])->assertRedirect();

    expect(GoalMovement::where('type', 'withdrawal')->sole()->period_start->toDateString())
        ->toBe('2026-11-25');
});

it('moves the full leftover even when it overshoots the target', function () {
    $this->goal->update(['target_amount' => 100_000]);
    $food = sweepBudget($this->household, 'Food', 2_000_000, 1_640_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => sweepChoices([$food]),
    ])->assertRedirect();

    $this->actingAs($this->owner)->get("{$this->base}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.balance', 360_000)
            ->where('goals.0.pct', 360)
            ->where('goals.0.over', true)
            ->where('goals.0.remaining', 0));

    expect(GoalMovement::sole()->amount)->toBe(360_000);
});

it('leaves the source budget spent figure and the period report untouched', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000, 250_000);
    sweepRule($this->goal, $food);

    $this->actingAs($this->owner)->post("{$this->base}/close", [
        'start' => '2026-11-25',
        'decisions' => sweepChoices([$food]),
    ])->assertRedirect();

    expect($food->fresh()->spent())->toBe(250_000)
        ->and($food->fresh()->movedToGoals())->toBe(750_000);

    $this->actingAs($this->owner)->get("{$this->base}/report?month=2026-10-27")
        ->assertInertia(fn (Assert $p) => $p
            ->where('spent', 250_000)
            ->where('budgets.0.spent', 250_000));
});

it('saves sweep selections on the goal form and rejects a claimed budget', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000);
    $gas = sweepBudget($this->household, 'Gas', 500_000, sort: 1);
    $trip = sweepBudget($this->household, 'Trip', 500_000, repeat: false, sort: 2);
    $fun = sweepBudget($this->household, 'Fun', 200_000, sort: 3);

    $this->actingAs($this->owner)->post("{$this->base}/goals", [
        'name' => 'Holiday',
        'target_amount' => 5_000_000,
        'sweep_budget_ids' => [$food->budget_id, $gas->budget_id],
    ])->assertRedirect();

    $holiday = $this->household->goals()->get()->firstWhere('name', 'Holiday');

    expect($holiday->sweepRules()->pluck('budget_id')->sort()->values()->all())
        ->toBe([$food->budget_id, $gas->budget_id]);

    $this->actingAs($this->owner)->post("{$this->base}/goals", [
        'name' => 'Other',
        'target_amount' => 1_000_000,
        'sweep_budget_ids' => [$food->budget_id],
    ])->assertSessionHasErrors('sweep_budget_ids');

    $this->actingAs($this->owner)->post("{$this->base}/goals", [
        'name' => 'Car',
        'target_amount' => 1_000_000,
        'sweep_budget_ids' => [$trip->budget_id],
    ])->assertSessionHasErrors('sweep_budget_ids.0');

    $this->actingAs($this->owner)->get("{$this->base}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->has('sweepBudgets', 1)
            ->where('sweepBudgets.0.id', $fun->budget_id));

    $this->actingAs($this->owner)->patch("{$this->base}/goals/{$holiday->id}", [
        'name' => 'Holiday',
        'target_amount' => 5_000_000,
        'sweep_budget_ids' => [$gas->budget_id],
    ])->assertRedirect();

    expect($holiday->sweepRules()->pluck('budget_id')->all())->toBe([$gas->budget_id]);
});

it('lets only members with edit rights manage sweep rules', function () {
    $food = sweepBudget($this->household, 'Food', 1_000_000);
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->patch("{$this->base}/goals/{$this->goal->id}", [
        'name' => 'Bali',
        'target_amount' => 1_000_000,
        'sweep_budget_ids' => [$food->budget_id],
    ])->assertForbidden();

    $this->actingAs($viewer)->get("{$this->base}/goals")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));

    expect(GoalSweepRule::count())->toBe(0);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("{$this->base}/goals")->assertNotFound();
    $this->actingAs($outsider)->post("{$this->base}/goals", [
        'name' => 'Nope',
        'target_amount' => 1_000,
        'sweep_budget_ids' => [$food->budget_id],
    ])->assertNotFound();
});
