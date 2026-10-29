<?php

use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}/transactions";
    $this->food = octoberBudget($this->household, 'Food', 2_000_000);
});

function octoberBudget(Household $h, string $name, int $allocated, string $period = '2026-10-01'): BudgetMonth
{
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated]);

    return BudgetMonth::factory()->for($budget)->create(['period_start' => $period, 'allocated' => $allocated, 'carried_in' => 0]);
}

function leftIn(BudgetMonth $month): int
{
    return BudgetMonth::with('transactions')->find($month->id)->balance();
}

function payload(BudgetMonth $month, array $over = []): array
{
    return array_merge([
        'type' => 'expense',
        'budget_id' => $month->budget_id,
        'amount' => 85_000,
        'note' => 'Groceries',
        'occurred_at' => '2026-10-15',
        'user_id' => $month->budget->household->owner_id,
    ], $over);
}

function entry(BudgetMonth $month, array $over = []): Transaction
{
    return Transaction::factory()->create(array_merge(['household_id' => $month->budget->household_id, 'budget_month_id' => $month->id], $over));
}

it('records an expense and lowers the budget', function () {
    $this->actingAs($this->owner)->post($this->base, payload($this->food))->assertRedirect();

    $t = Transaction::first();

    expect($t->amount)->toBe(85_000)
        ->and($t->type)->toBe(TransactionTypeEnum::EXPENSE)
        ->and($t->note)->toBe('Groceries')
        ->and(leftIn($this->food))->toBe(1_915_000);
});

it('records income and raises the budget', function () {
    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['type' => 'income', 'amount' => 500_000]))->assertRedirect();

    expect(leftIn($this->food))->toBe(2_500_000);
});

it('stores amount and note encrypted', function () {
    $this->actingAs($this->owner)->post($this->base, payload($this->food))->assertRedirect();

    expect(DB::table('transactions')->value('amount'))->not->toBe('85000')
        ->and(DB::table('transactions')->value('note'))->not->toBe('Groceries');
});

it('creates the month row on the first transaction of a new month', function () {
    $this->travelTo('2026-11-02 10:00:00');
    $this->household->budgets()->first()->update(['repeat_monthly' => true]);

    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['occurred_at' => '2026-11-02']))->assertRedirect();

    $november = BudgetMonth::where('budget_id', $this->food->budget_id)->whereDate('period_start', '2026-11-01')->first();

    expect($november)->not->toBeNull()->and($november->allocated)->toBe(2_000_000);
});

it('rejects a date in a closed month', function () {
    $september = octoberBudget($this->household, 'Gas', 700_000, '2026-09-01');
    $september->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->post($this->base, payload($september, ['occurred_at' => '2026-09-20']))
        ->assertSessionHasErrors('occurred_at');

    expect(Transaction::count())->toBe(0);
});

it('rejects a past month that has no budget', function () {
    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['occurred_at' => '2026-08-10']))
        ->assertSessionHasErrors('occurred_at');
});

it('rejects future dates and bad input', function () {
    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['occurred_at' => '2026-10-20']))->assertSessionHasErrors('occurred_at');
    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['amount' => 0, 'type' => 'transfer_in']))->assertSessionHasErrors(['amount', 'type']);
});

it('rejects a budget from another household and a payer who is not a member', function () {
    $foreign = octoberBudget(Household::factory()->create(), 'Food', 1_000_000);

    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['budget_id' => $foreign->budget_id]))->assertSessionHasErrors('budget_id');
    $this->actingAs($this->owner)->post($this->base, payload($this->food, ['user_id' => User::factory()->create()->id]))->assertSessionHasErrors('user_id');
});

it('adds a correction in the current month for a locked entry', function () {
    $september = octoberBudget($this->household, 'Gas', 700_000, '2026-09-01');
    $old = entry($september, ['amount' => 100_000, 'occurred_at' => '2026-09-10 12:00:00']);
    $september->update(['closed_at' => now()]);
    octoberBudget($this->household, 'Spare', 1);
    $gasOctober = BudgetMonth::factory()->for($september->budget)->create(['period_start' => '2026-10-01', 'allocated' => 700_000]);

    $this->actingAs($this->owner)->get("{$this->base}/create?corrects={$old->id}")
        ->assertInertia(fn (Assert $p) => $p->component('transactions/form')
            ->where('defaults.type', 'income')->where('defaults.amount', '100000')->where('defaults.corrects_transaction_id', $old->id));

    $this->actingAs($this->owner)->post($this->base, payload($gasOctober, ['type' => 'income', 'amount' => 100_000, 'corrects_transaction_id' => $old->id]))->assertRedirect();

    expect(Transaction::latest('id')->first())->corrects_transaction_id->toBe($old->id)
        ->and($old->fresh()->budgetMonth->closed_at)->not->toBeNull();
});

it('edits an open entry, including moving it to another budget', function () {
    $gas = octoberBudget($this->household, 'Gasoline', 700_000);
    $t = entry($this->food, ['amount' => 50_000]);

    $this->actingAs($this->owner)->put("{$this->base}/{$t->id}", payload($gas, ['amount' => 70_000]))
        ->assertRedirect("{$this->base}/{$t->id}");

    expect($t->fresh())->amount->toBe(70_000)->budget_month_id->toBe($gas->id)
        ->and(leftIn($this->food))->toBe(2_000_000)
        ->and(leftIn($gas))->toBe(630_000);
});

it('refuses to edit or delete entries of a closed month', function () {
    $t = entry($this->food, ['amount' => 50_000]);
    $this->food->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->put("{$this->base}/{$t->id}", payload($this->food))->assertSessionHasErrors('occurred_at');
    $this->actingAs($this->owner)->delete("{$this->base}/{$t->id}")->assertSessionHasErrors('occurred_at');
    $this->actingAs($this->owner)->get("{$this->base}/{$t->id}/edit")->assertRedirect("{$this->base}/{$t->id}");

    expect($t->fresh()->amount)->toBe(50_000)->and($t->fresh()->trashed())->toBeFalse();
});

it('soft deletes and updates the balance', function () {
    $t = entry($this->food, ['amount' => 50_000]);

    $this->actingAs($this->owner)->delete("{$this->base}/{$t->id}")->assertRedirect();

    expect(Transaction::withTrashed()->find($t->id)->trashed())->toBeTrue()->and(leftIn($this->food))->toBe(2_000_000);
});

it('does not edit moved money here', function () {
    $t = entry($this->food, ['type' => TransactionTypeEnum::TRANSFER_OUT, 'amount' => 10_000]);

    $this->actingAs($this->owner)->put("{$this->base}/{$t->id}", payload($this->food))->assertSessionHasErrors('type');
    $this->actingAs($this->owner)->delete("{$this->base}/{$t->id}")->assertSessionHasErrors('type');
});

it('lets viewers read but not change anything', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);
    $t = entry($this->food);

    $this->actingAs($viewer)->get($this->base)->assertOk();
    $this->actingAs($viewer)->get("{$this->base}/{$t->id}")->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
    $this->actingAs($viewer)->get("{$this->base}/create")->assertForbidden();
    $this->actingAs($viewer)->post($this->base, payload($this->food))->assertForbidden();
    $this->actingAs($viewer)->put("{$this->base}/{$t->id}", payload($this->food))->assertForbidden();
    $this->actingAs($viewer)->delete("{$this->base}/{$t->id}")->assertForbidden();
});

it('hides the household from outsiders', function () {
    $outsider = User::factory()->create();
    $t = entry($this->food);

    $this->actingAs($outsider)->get($this->base)->assertNotFound();
    $this->actingAs($outsider)->post($this->base, payload($this->food))->assertNotFound();
    $this->actingAs($outsider)->get("{$this->base}/{$t->id}")->assertNotFound();
});

it('does not open another household\'s transaction through your own url', function () {
    $foreign = entry(octoberBudget(Household::factory()->create(), 'Food', 1_000_000));

    $this->actingAs($this->owner)->get("{$this->base}/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->owner)->delete("{$this->base}/{$foreign->id}")->assertNotFound();
});

it('filters history by month, type, person and search text', function () {
    $wife = User::factory()->create();
    $this->household->members()->attach($wife->id, ['role' => 'editor']);
    $gas = octoberBudget($this->household, 'Gasoline', 700_000);
    $sept = octoberBudget($this->household, 'Old', 1, '2026-09-01');

    entry($this->food, ['note' => 'Groceries', 'amount' => 85_000, 'user_id' => $this->owner->id]);
    entry($this->food, ['note' => 'Bonus', 'type' => TransactionTypeEnum::INCOME, 'amount' => 500_000, 'user_id' => $wife->id]);
    entry($gas, ['note' => 'Petrol', 'amount' => 50_000, 'user_id' => $wife->id]);
    entry($sept, ['note' => 'Last month', 'occurred_at' => '2026-09-10 12:00:00']);

    $titles = fn (string $query) => $this->actingAs($this->owner)->get("{$this->base}{$query}")
        ->viewData('page')['props']['transactions'] ?? [];

    $get = fn (string $query) => collect($titles($query))->pluck('title')->sort()->values()->all();

    expect($get(''))->toBe(['Bonus', 'Groceries', 'Petrol'])
        ->and($get('?type=income'))->toBe(['Bonus'])
        ->and($get("?person={$wife->id}"))->toBe(['Bonus', 'Petrol'])
        ->and($get('?q=groc'))->toBe(['Groceries'])
        ->and($get('?q=gasoline'))->toBe(['Petrol'])
        ->and($get('?month=2026-09'))->toBe(['Last month']);
});

it('shows the entry, what is left and whether it is locked', function () {
    $t = entry($this->food, ['amount' => 85_000]);

    $this->actingAs($this->owner)->get("{$this->base}/{$t->id}")
        ->assertInertia(fn (Assert $p) => $p->component('transactions/show')
            ->where('transaction.left', 1_915_000)->where('locked', false)->where('canEdit', true));

    $this->food->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->get("{$this->base}/{$t->id}")->assertInertia(fn (Assert $p) => $p->where('locked', true));
});
