<?php

use App\Actions\CloseMonth;
use App\Enums\TransactionTypeEnum;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use Carbon\Carbon;

function monthWith(Household $h, string $name, int $allocated, array $spend = [], int $carriedIn = 0): BudgetMonth
{
    $budget = Budget::factory()->for($h)->create(['name' => $name, 'default_amount' => $allocated]);
    $month = BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => $allocated,
        'carried_in' => $carriedIn,
    ]);

    foreach ($spend as $amount) {
        Transaction::factory()
            ->create([
                'household_id' => $h->id,
                'budget_month_id' => $month->id,
                'amount' => $amount,
            ]);
    }

    return $month;
}

function nextMonth(BudgetMonth $october): ?BudgetMonth
{
    return BudgetMonth::where('budget_id', $october->budget_id)->whereDate('period_start', '2026-11-01')->first();
}

beforeEach(function () {
    $this->household = Household::factory()->create();
    $this->period = Carbon::parse('2026-10-01');
    $this->close = app(CloseMonth::class);
});

it('carries the leftover into next month', function () {
    $food = monthWith($this->household, 'Food', 2_000_000, [1_640_000]);

    $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']);

    expect(nextMonth($food))
        ->carried_in->toBe(360_000)
        ->allocated->toBe(2_000_000)
        ->and($food->fresh()->closed_at)->not->toBeNull()
        ->and($food->fresh()->carry_decision)->toBe('carry');
});

it('starts fresh when the budget is reset', function () {
    $gas = monthWith($this->household, 'Gasoline', 700_000, [590_000]);

    $this->close->handle($this->household, $this->period, [$gas->budget_id => 'reset']);

    expect(nextMonth($gas)->carried_in)->toBe(0);
});

it('carries a deficit as a negative amount', function () {
    $transport = monthWith($this->household, 'Transport', 600_000, [710_000]);

    $this->close->handle($this->household, $this->period, [$transport->budget_id => 'carry']);

    expect(nextMonth($transport)->carried_in)->toBe(-110_000);
});

it('counts income and transfers in the leftover', function () {
    $food = monthWith($this->household, 'Food', 1_000_000, [200_000]);

    foreach (
        [
            [TransactionTypeEnum::INCOME, 100_000],
            [TransactionTypeEnum::TRANSFER_OUT, 50_000],
            [TransactionTypeEnum::TRANSFER_IN, 20_000],
        ] as [$type, $amount]
    ) {
        Transaction::factory()->type($type)->create([
            'household_id' => $this->household->id,
            'budget_month_id' => $food->id,
            'amount' => $amount,
        ]);
    }

    // 1_000_000 - 200_000 + 100_000 - 50_000 + 20_000
    $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']);

    expect(nextMonth($food)->carried_in)->toBe(870_000);
});

it('gives the November total shown on the Close October screen', function () {
    $plan = [
        'Food' => [2_000_000, 1_640_000, 'carry'],
        'Internet' => [400_000, 400_000, 'carry'],
        'Kitchen items' => [800_000, 530_000, 'carry'],
        'Gasoline' => [700_000, 590_000, 'reset'],
        'Transport' => [600_000, 710_000, 'carry'],
    ];

    $decisions = [];
    foreach ($plan as $name => [$allocated, $spent, $decision]) {
        $decisions[monthWith($this->household, $name, $allocated, [$spent])->budget_id] = $decision;
    }

    $this->close->handle($this->household, $this->period, $decisions);

    $total = BudgetMonth::whereDate('period_start', '2026-11-01')->get()->sum(fn ($m) => $m->allocated + $m->carried_in);

    expect($total)->toBe(5_020_000);
});

it('ignores months that are already closed', function () {
    $food = monthWith($this->household, 'Food', 2_000_000, [1_640_000]);

    $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']);
    $this->close->handle($this->household, $this->period, [$food->budget_id => 'reset']);

    expect(nextMonth($food)->carried_in)->toBe(360_000)
        ->and($food->fresh()->carry_decision)->toBe('carry');
});

it('saves nothing when a decision is missing', function () {
    $food = monthWith($this->household, 'Food', 2_000_000, [1_000_000]);
    $gas = monthWith($this->household, 'Gasoline', 700_000, [500_000]);

    expect(fn () => $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']))
        ->toThrow(InvalidArgumentException::class);

    expect($food->fresh()->closed_at)->toBeNull()
        ->and($gas->fresh()->closed_at)->toBeNull()
        ->and(BudgetMonth::whereDate('period_start', '2026-11-01')->count())->toBe(0);
});

it('keeps an amount already set for next month', function () {
    $food = monthWith($this->household, 'Food', 2_000_000, [1_640_000]);
    BudgetMonth::factory()->for($food->budget)->create(['period_start' => '2026-11-01', 'allocated' => 750_000, 'carried_in' => 0]);

    $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']);

    expect(nextMonth($food))->allocated->toBe(750_000)->carried_in->toBe(360_000)
        ->and(BudgetMonth::where('budget_id', $food->budget_id)->count())->toBe(2);
});

it('locks transactions in a closed month', function () {
    $food = monthWith($this->household, 'Food', 2_000_000, [100_000]);
    $this->close->handle($this->household, $this->period, [$food->budget_id => 'carry']);

    expect(fn () => Transaction::first()->update(['note' => 'changed']))->toThrow(LogicException::class);
    expect(fn () => Transaction::first()->delete())->toThrow(LogicException::class);
    expect(fn () => Transaction::factory()->create(['household_id' => $this->household->id, 'budget_month_id' => $food->id]))
        ->toThrow(LogicException::class);
});

it('does not touch another household', function () {
    $mine = monthWith($this->household, 'Food', 1_000_000, [100_000]);
    $theirs = monthWith(Household::factory()->create(), 'Food', 1_000_000, [100_000]);

    $this->close->handle($this->household, $this->period, [$mine->budget_id => 'carry']);

    expect($theirs->fresh()->closed_at)->toBeNull();
});
