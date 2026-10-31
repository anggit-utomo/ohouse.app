<?php

use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-28 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->wife = User::factory()->create();
    $this->household->members()->attach($this->wife->id, ['role' => 'editor']);
    $this->url = "/h/{$this->household->id}/report";
});

function repMonth(Household $h, string $name, string $period = '2026-10-01'): BudgetMonth
{
    return BudgetMonth::factory()->for(Budget::factory()->for($h)->create(['name' => $name]))->create(['period_start' => $period]);
}

function repEntry(BudgetMonth $m, User $user, int $amount, TransactionTypeEnum $type = TransactionTypeEnum::EXPENSE, array $over = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'household_id' => $m->budget->household_id,
        'budget_month_id' => $m->id,
        'user_id' => $user->id,
        'type' => $type,
        'amount' => $amount,
        'occurred_at' => substr((string) $m->getRawOriginal('period_start'), 0, 7).'-10 12:00:00',
    ], $over));
}

function repScenario(Household $h, User $me, User $wife): array
{
    $m = collect(['Food', 'Internet', 'Kitchen items', 'Gasoline', 'Transport'])->mapWithKeys(fn ($n) => [$n => repMonth($h, $n)]);

    repEntry($m['Food'], $me, 1_000_000);
    repEntry($m['Food'], $wife, 640_000);
    repEntry($m['Transport'], $me, 710_000);
    repEntry($m['Internet'], $me, 400_000);
    repEntry($m['Gasoline'], $me, 140_000);
    repEntry($m['Gasoline'], $wife, 450_000);
    repEntry($m['Kitchen items'], $wife, 530_000);

    return $m->all();
}

it('totals the period by budget and by person, biggest first', function () {
    repScenario($this->household, $this->owner, $this->wife);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->component('report/show')->where('month', '2026-10-01')->where('hasData', true)->where('spent', 3_870_000)->where('income', 0)
        ->where('budgets.0.name', 'Food')->where('budgets.0.spent', 1_640_000)->where('budgets.0.pct', 42)
        ->where('budgets.1.name', 'Transport')->where('budgets.1.pct', 18)
        ->where('budgets.2.name', 'Gasoline')->where('budgets.2.spent', 590_000)->where('budgets.2.pct', 15)
        ->where('budgets.3.name', 'Kitchen items')->where('budgets.3.pct', 14)
        ->where('budgets.4.name', 'Internet')->where('budgets.4.pct', 10)
        ->where('people.0.id', $this->owner->id)->where('people.0.spent', 2_250_000)->where('people.0.pct', 58)
        ->where('people.1.id', $this->wife->id)->where('people.1.spent', 1_620_000)->where('people.1.pct', 42));
});

it('leaves moved money out and shows income separately', function () {
    $food = repMonth($this->household, 'Food');
    repEntry($food, $this->owner, 100_000);
    repEntry($food, $this->owner, 999_000, TransactionTypeEnum::TRANSFER_OUT, ['transfer_id' => 'a8f7c9d2-0000-4000-8000-000000000001']);
    repEntry($food, $this->owner, 999_000, TransactionTypeEnum::TRANSFER_IN, ['transfer_id' => 'a8f7c9d2-0000-4000-8000-000000000001']);
    repEntry($food, $this->owner, 500_000, TransactionTypeEnum::INCOME);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('spent', 100_000)->where('income', 500_000));
});

it('ignores deleted entries', function () {
    $food = repMonth($this->household, 'Food');
    repEntry($food, $this->owner, 100_000);
    repEntry($food, $this->owner, 40_000)->delete();

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('spent', 100_000));
});

it('compares with the period before', function () {
    repScenario($this->household, $this->owner, $this->wife);

    $september = repMonth($this->household, 'Food', '2026-09-01');
    repEntry($september, $this->owner, 3_990_000);
    $september->update(['closed_at' => now()]);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('previous.label', 'September')->where('previous.spent', 3_990_000)->where('change', -120_000));
});

it('has no comparison when there is no period before', function () {
    repScenario($this->household, $this->owner, $this->wife);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('previous', null)->where('change', null));
});

it('subtracts a correction from what it corrects, even from a closed period', function () {
    $september = repMonth($this->household, 'Food', '2026-09-01');
    $wrongExpense = repEntry($september, $this->owner, 100_000);
    $september->update(['closed_at' => now()]);
    $october = repMonth($this->household, 'Food');
    repEntry($october, $this->owner, 300_000);
    repEntry($october, $this->owner, 100_000, TransactionTypeEnum::INCOME, ['corrects_transaction_id' => $wrongExpense->id]);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('spent', 200_000)->where('income', 0)->where('budgets.0.spent', 200_000));
});

it('subtracts an expense that corrects an income from income', function () {
    $food = repMonth($this->household, 'Food');
    $bonus = repEntry($food, $this->owner, 500_000, TransactionTypeEnum::INCOME);
    repEntry($food, $this->owner, 120_000, TransactionTypeEnum::EXPENSE, ['corrects_transaction_id' => $bonus->id]);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('spent', 0)->where('income', 380_000));
});

it('opens a past period and clamps the rest to the open one', function () {
    $september = repMonth($this->household, 'Food', '2026-09-01');
    repEntry($september, $this->owner, 250_000);
    $september->update(['closed_at' => now()]);
    repMonth($this->household, 'Gas'); // the open period, starting 2026-10-01

    $this->actingAs($this->owner)->get("{$this->url}?month=2026-09")->assertInertia(fn (Assert $p) => $p->where('month', '2026-09-01')->where('spent', 250_000));
    $this->actingAs($this->owner)->get("{$this->url}?month=2027-03")->assertInertia(fn (Assert $p) => $p->where('month', '2026-10-01'));
    $this->actingAs($this->owner)->get("{$this->url}?month=garbage")->assertInertia(fn (Assert $p) => $p->where('month', '2026-10-01'));
});

it('shows an empty period', function () {
    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p
        ->where('hasData', false)->where('spent', 0)->has('budgets', 0)->has('people', 0));
});

it('keeps other households out of the numbers', function () {
    $other = Household::factory()->create();
    repEntry(repMonth($other, 'Food'), $other->owner, 900_000);
    repEntry(repMonth($this->household, 'Food'), $this->owner, 100_000);

    $this->actingAs($this->owner)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('spent', 100_000));
});

it('is open to viewers and closed to outsiders', function () {
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get($this->url)->assertOk();
    $this->actingAs(User::factory()->create())->get($this->url)->assertNotFound();
});
