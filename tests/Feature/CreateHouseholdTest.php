<?php

use App\Actions\CreateHousehold;
use App\Enums\HouseholdRoleEnum;
use App\Models\BudgetMonth;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('creates a household with the creator as owner and starter budgets', function () {
    $user = User::factory()->create();

    $household = app(CreateHousehold::class)->handle($user, 'Our household', 'IDR', 1, ['Food', 'Gasoline']);

    expect($user->fresh()->current_household_id)->toBe($household->id)
        ->and($user->roleIn($household))->toBe(HouseholdRoleEnum::OWNER)
        ->and($household->budgets)->toHaveCount(2)
        ->and(BudgetMonth::count())->toBe(2);
});

it('stores names and amounts encrypted', function () {
    $user = User::factory()->create();
    $household = app(CreateHousehold::class)->handle($user, 'Our household', 'IDR', 1, ['Food']);
    $household->budgets->first()->update(['default_amount' => 2_000_000]);

    expect(DB::table('households')->value('name'))->not->toBe('Our household')
        ->and(DB::table('budgets')->value('name'))->not->toBe('Food')
        ->and(DB::table('budgets')->value('default_amount'))->not->toBe('2000000')
        ->and($household->budgets()->first()->default_amount)->toBe(2_000_000);
});
