<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create();
    $this->url = "/h/{$this->household->id}/budgets";
});

function novemberOf(Budget $budget): ?BudgetMonth
{
    return BudgetMonth::where('budget_id', $budget->id)->whereDate('period_start', '2026-11-01')->first();
}

it('saves amounts, adds a category and archives removed ones', function () {
    $food = Budget::factory()->for($this->household)->create(['name' => 'Food', 'default_amount' => 1_000_000]);
    $old = Budget::factory()->for($this->household)->create(['name' => 'Old']);

    $this->actingAs($this->household->owner)->put($this->url, [
        'period' => '2026-11',
        'repeat' => true,
        'budgets' => [
            ['id' => $food->id, 'name' => 'Food', 'amount' => 2_000_000],
            ['id' => null, 'name' => 'Gasoline', 'amount' => 700_000],
        ],
    ])->assertRedirect();

    $gasoline = $this->household->budgets()->get()->firstWhere('name', 'Gasoline');

    expect($food->fresh()->default_amount)->toBe(2_000_000)
        ->and(novemberOf($food)->allocated)->toBe(2_000_000)
        ->and($gasoline)->not->toBeNull()
        ->and(novemberOf($gasoline)->allocated)->toBe(700_000)
        ->and($gasoline->repeat_monthly)->toBeTrue()
        ->and($old->fresh()->archived_at)->not->toBeNull()
        ->and($food->fresh()->archived_at)->toBeNull();
});

it('only changes the month row when editing the current month', function () {
    $food = Budget::factory()->for($this->household)->create(['name' => 'Food']);
    BudgetMonth::factory()->for($food)->create([
        'period_start' => '2026-10-01',
        'allocated' => 500_000,
        'carried_in' => 120_000,
    ]);

    $this->actingAs($this->household->owner)->put($this->url, [
        'period' => '2026-10',
        'repeat' => false,
        'budgets' => [['id' => $food->id, 'name' => 'Food', 'amount' => 900_000]],
    ])->assertRedirect();

    $october = BudgetMonth::where('budget_id', $food->id)->whereDate('period_start', '2026-10-01')->first();

    expect($october->allocated)->toBe(900_000)
        ->and($october->carried_in)->toBe(120_000) // carry-over is untouched
        ->and($food->fresh()->repeat_monthly)->toBeFalse();
});

it('rejects past months', function () {
    $this->actingAs($this->household->owner)
        ->put($this->url, ['period' => '2026-09', 'repeat' => true, 'budgets' => []])
        ->assertSessionHasErrors('period');
});

it('rejects a month that is already closed', function () {
    $food = Budget::factory()->for($this->household)->create();
    BudgetMonth::factory()->for($food)->create(['period_start' => '2026-10-01', 'closed_at' => now()]);

    $this->actingAs($this->household->owner)
        ->put($this->url, ['period' => '2026-10', 'repeat' => true, 'budgets' => []])
        ->assertSessionHasErrors('period');
});

it('rejects a budget id from another household', function () {
    $foreign = Budget::factory()->create();

    $this->actingAs($this->household->owner)->put($this->url, [
        'period' => '2026-11',
        'repeat' => true,
        'budgets' => [['id' => $foreign->id, 'name' => 'Hacked', 'amount' => 1]],
    ])->assertSessionHasErrors('budgets.0.id');

    expect($foreign->fresh()->default_amount)->toBe(500_000);
});

it('validates names and amounts', function () {
    $this->actingAs($this->household->owner)->put($this->url, [
        'period' => '2026-11',
        'repeat' => true,
        'budgets' => [['id' => null, 'name' => '', 'amount' => -5]],
    ])->assertSessionHasErrors(['budgets.0.name', 'budgets.0.amount']);
});

it('lets viewers see the page but not save', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->withoutExceptionHandling();

    $response = $this->actingAs($viewer)->get($this->url);
    $response->assertOk(); // shows the real status if it isn't 200

    $response->assertInertia(fn (Assert $page) => $page->component('budgets/edit')->where('canEdit', false));

    $this->withExceptionHandling();

    $this->actingAs($viewer)->put(
        $this->url,
        ['period' => '2026-11', 'repeat' => true, 'budgets' => []]
    )->assertForbidden();
});

it('returns 404 to people outside the household', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get($this->url)->assertNotFound();
    $this->actingAs($outsider)->put(
        $this->url,
        ['period' => '2026-11', 'repeat' => true, 'budgets' => []]
    )->assertNotFound();
});

it('uses the current month when none is given or the month is in the past', function () {
    Budget::factory()->for($this->household)->create();

    foreach ([$this->url, "{$this->url}?month=2026-09", "{$this->url}?month=garbage"] as $url) {
        $this->actingAs($this->household->owner)->get($url)
            ->assertInertia(fn (Assert $page) => $page->component('budgets/edit')->where('period', '2026-10'));
    }
});

it('shows the amount for the chosen month, falling back to the default', function () {
    $food = Budget::factory()->for($this->household)->create(['name' => 'Food', 'default_amount' => 2_000_000]);
    $gas = Budget::factory()->for($this->household)->create(['name' => 'Gasoline', 'default_amount' => 700_000]);
    BudgetMonth::factory()->for($gas)->create(['period_start' => '2026-11-01', 'allocated' => 650_000]);

    $this->actingAs($this->household->owner)->get("{$this->url}?month=2026-11")
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/edit')
            ->where('period', '2026-11')
            ->where('budgets.0.amount', 2_000_000)
            ->where('budgets.1.amount', 650_000));
});
