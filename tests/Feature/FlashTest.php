<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create(['period_start' => '2026-10-01']);
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}";
    $this->month = BudgetMonth::factory()->for(Budget::factory()->for($this->household)->create(['name' => 'Food']))
        ->create(['period_start' => '2026-10-01', 'allocated' => 1_000_000]);
    $this->entry = fn (array $over = []) => array_merge([
        'type' => 'expense',
        'budget_id' => $this->month->budget_id,
        'amount' => 50_000,
        'occurred_at' => '2026-10-15',
        'user_id' => $this->owner->id,
    ], $over);
});

it('flashes a message after each action', function () {
    $this->actingAs($this->owner)->put("{$this->base}/budgets", ['budgets' => [
        ['id' => $this->month->budget_id, 'name' => 'Food', 'amount' => 900_000, 'repeat' => true],
    ]])->assertSessionHas('success', 'Budgets saved.');

    $this->actingAs($this->owner)->post("{$this->base}/transactions", ($this->entry)())->assertSessionHas('success', 'Expense saved.');
    $this->actingAs($this->owner)->post("{$this->base}/transactions", ($this->entry)(['type' => 'income']))->assertSessionHas('success', 'Income saved.');

    $t = Transaction::first();

    $this->actingAs($this->owner)->put("{$this->base}/transactions/{$t->id}", ($this->entry)(['amount' => 60_000]))->assertSessionHas('success', 'Transaction updated.');
    $this->actingAs($this->owner)->delete("{$this->base}/transactions/{$t->id}")->assertSessionHas('success', 'Transaction deleted.');
    $this->actingAs($this->owner)->post("{$this->base}/family/invite")->assertSessionHas('success', 'New pair code created.');
});

it('hands the message to the page after the redirect', function () {
    $this->actingAs($this->owner)->followingRedirects()
        ->put("{$this->base}/budgets", ['budgets' => [['id' => $this->month->budget_id, 'name' => 'Food', 'amount' => 900_000, 'repeat' => true]]])
        ->assertInertia(fn (Assert $p) => $p->component('budgets/edit')->where('flash.success', 'Budgets saved.'));
});

it('does not flash anything when an action is refused', function () {
    $this->actingAs($this->owner)->post("{$this->base}/transactions", ($this->entry)(['amount' => 0]))
        ->assertSessionMissing('success')->assertSessionHasErrors('amount');
});
