<?php

use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;

it('lets only members view a household', function () {
    $household = Household::factory()->create();

    expect($household->owner->can('view', $household))->toBeTrue()
        ->and(User::factory()->create()->can('view', $household))->toBeFalse();
});

it('lets viewers read but not edit', function () {
    $household = Household::factory()->create();
    $viewer = User::factory()->create();
    $household->members()->attach($viewer->id, ['role' => 'viewer']);

    expect($viewer->can('view', $household))->toBeTrue()
        ->and($viewer->can('edit', $household))->toBeFalse()
        ->and($household->owner->can('manage', $household))->toBeTrue();
});

it('blocks editing a transaction of another household', function () {
    $month = BudgetMonth::factory()->create();
    $transaction = Transaction::factory()->create([
        'budget_month_id' => $month->id,
        'household_id' => $month->budget->household_id,
    ]);
    $outsider = Household::factory()->create()->owner;

    expect($outsider->can('view', $transaction))->toBeFalse()
        ->and($outsider->can('update', $transaction))->toBeFalse()
        ->and($month->budget->household->owner->can('update', $transaction))->toBeTrue();
});
