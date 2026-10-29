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
    $this->base = "/h/{$this->household->id}/transfers";
    $this->gas = moveBudget($this->household, 'Gasoline', 700_000, 590_000);      // 110.000 left
    $this->transport = moveBudget($this->household, 'Transport', 600_000, 710_000); // over by 110.000
});

function moveBudget(Household $h, string $name, int $allocated, int $spent = 0): BudgetMonth
{
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated]);
    $month = BudgetMonth::factory()->for($budget)->create(['period_start' => '2026-10-01', 'allocated' => $allocated, 'carried_in' => 0]);

    if ($spent) {
        Transaction::factory()->create(['household_id' => $h->id, 'budget_month_id' => $month->id, 'amount' => $spent, 'occurred_at' => '2026-10-10 12:00:00']);
    }

    return $month;
}

function moveLeft(BudgetMonth $month): int
{
    return BudgetMonth::with('transactions')->find($month->id)->balance();
}

function movePayload(BudgetMonth $from, BudgetMonth $to, array $over = []): array
{
    return array_merge(['from_budget_id' => $from->budget_id, 'to_budget_id' => $to->budget_id, 'amount' => 100_000, 'note' => null], $over);
}

it('moves money and creates two linked entries', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport))
        ->assertRedirect("/h/{$this->household->id}");

    $out = Transaction::where('type', TransactionTypeEnum::TRANSFER_OUT)->first();
    $in = Transaction::where('type', TransactionTypeEnum::TRANSFER_IN)->first();

    expect(moveLeft($this->gas))->toBe(10_000)
        ->and(moveLeft($this->transport))->toBe(-10_000)
        ->and($out->transfer_id)->not->toBeNull()->and($out->transfer_id)->toBe($in->transfer_id)
        ->and($out->amount)->toBe(100_000)->and($in->amount)->toBe(100_000)
        ->and($out->budget_month_id)->toBe($this->gas->id)->and($in->budget_month_id)->toBe($this->transport->id)
        ->and($out->user_id)->toBe($this->owner->id);
});

it('can move everything that is left', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport, ['amount' => 110_000]))->assertRedirect();

    expect(moveLeft($this->gas))->toBe(0);
});

it('refuses to move more than the source has left and saves nothing', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport, ['amount' => 110_001]))
        ->assertSessionHasErrors('amount');

    expect(Transaction::where('type', TransactionTypeEnum::TRANSFER_OUT)->count())->toBe(0)->and(moveLeft($this->gas))->toBe(110_000);
});

it('cannot take money from a budget that is already over', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->transport, $this->gas, ['amount' => 1]))->assertSessionHasErrors('amount');
});

it('rejects the same budget twice, bad amounts and budgets of another household', function () {
    $foreign = moveBudget(Household::factory()->create(), 'Food', 1_000_000);

    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->gas))->assertSessionHasErrors('to_budget_id');
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport, ['amount' => 0]))->assertSessionHasErrors('amount');
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $foreign))->assertSessionHasErrors('to_budget_id');
    $this->actingAs($this->owner)->post($this->base, movePayload($foreign, $this->gas))->assertSessionHasErrors('from_budget_id');

    expect(Transaction::whereNotNull('transfer_id')->count())->toBe(0);
});

it('refuses when this month is closed', function () {
    $this->transport->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport))->assertSessionHasErrors('occurred_at');

    expect(Transaction::whereNotNull('transfer_id')->count())->toBe(0);
});

it('creates the month row for a budget that has none yet', function () {
    $budget = Budget::factory()->for($this->household)->create(['name' => 'Savings', 'default_amount' => 300_000, 'repeat_monthly' => true]);

    $this->actingAs($this->owner)->post($this->base, [
        'from_budget_id' => $budget->id,
        'to_budget_id' => $this->transport->budget_id,
        'amount' => 50_000,
    ])->assertRedirect();

    expect(moveLeft(BudgetMonth::where('budget_id', $budget->id)->first()))->toBe(250_000);
});

it('undoes both sides of a move', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport));
    $id = Transaction::whereNotNull('transfer_id')->value('transfer_id');

    $this->actingAs($this->owner)->delete("{$this->base}/{$id}")->assertRedirect();

    expect(Transaction::whereNotNull('transfer_id')->count())->toBe(0)
        ->and(moveLeft($this->gas))->toBe(110_000)->and(moveLeft($this->transport))->toBe(-110_000);
});

it('will not undo a move in a closed month', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport));
    $id = Transaction::whereNotNull('transfer_id')->value('transfer_id');
    $this->gas->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->delete("{$this->base}/{$id}")->assertSessionHasErrors('transfer');

    expect(Transaction::whereNotNull('transfer_id')->count())->toBe(2);
});

it('does not undo another household\'s move', function () {
    $other = Household::factory()->create();
    $a = moveBudget($other, 'A', 500_000);
    $b = moveBudget($other, 'B', 500_000);
    $this->actingAs($other->owner)->post("/h/{$other->id}/transfers", movePayload($a, $b));
    $id = Transaction::whereNotNull('transfer_id')->value('transfer_id');

    $this->actingAs($this->owner)->delete("{$this->base}/{$id}")->assertNotFound();

    expect(Transaction::whereNotNull('transfer_id')->count())->toBe(2);
});

it('shows a move as one row in history and on its detail page', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport));

    $this->actingAs($this->owner)->get("/h/{$this->household->id}/transactions")->assertInertia(fn (Assert $p) => $p
        ->has('transactions', 3) // the two earlier expenses and one move
        ->where('transactions.0.title', 'Moved Gasoline → Transport')
        ->where('transactions.0.kind', 'transfer')->where('transactions.0.signed', 0)->where('transactions.0.amount', 100_000));

    $out = Transaction::where('type', TransactionTypeEnum::TRANSFER_OUT)->first();

    $this->actingAs($this->owner)->get("/h/{$this->household->id}/transactions/{$out->id}")->assertInertia(fn (Assert $p) => $p
        ->where('isTransfer', true)->where('moved.from', 'Gasoline')->where('moved.to', 'Transport')->where('transferId', $out->transfer_id));
});

it('keeps moves out of the expense and income filters', function () {
    $this->actingAs($this->owner)->post($this->base, movePayload($this->gas, $this->transport));

    $this->actingAs($this->owner)->get("/h/{$this->household->id}/transactions?type=expense")->assertInertia(fn (Assert $p) => $p->has('transactions', 2));
});

it('opens the page with budgets and preselected ones', function () {
    $this->actingAs($this->owner)->get("{$this->base}/create?from={$this->gas->budget_id}&to={$this->transport->budget_id}")
        ->assertInertia(fn (Assert $p) => $p->component('transfers/create')
            ->where('budgets.0.name', 'Gasoline')->where('budgets.0.left', 110_000)->where('budgets.1.left', -110_000)
            ->where('defaults.from_budget_id', (string) $this->gas->budget_id)->where('defaults.to_budget_id', (string) $this->transport->budget_id));
});

it('lets viewers see nothing here and hides the household from outsiders', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->base}/create")->assertForbidden();
    $this->actingAs($viewer)->post($this->base, movePayload($this->gas, $this->transport))->assertForbidden();

    $this->actingAs(User::factory()->create())->get("{$this->base}/create")->assertNotFound();
});
