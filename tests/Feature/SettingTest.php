<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Currencies;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create(['name' => 'Our home', 'period_start' => '2026-10-01']);
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}";
    $this->month = BudgetMonth::factory()->for(
        Budget::factory()->for($this->household)->create(['default_amount' => 2_000_000])
    )
        ->create(['period_start' => '2026-10-01', 'allocated' => 2_000_000, 'carried_in' => 50_000]);
});

it('lists the currencies and says whether the currency is locked', function () {
    $this->actingAs($this->owner)->get("{$this->base}/settings")->assertInertia(fn (Assert $p) => $p
        ->component('settings/household')->where('household.currency', 'IDR')->where('currencyLocked', false)
        ->where('isOwner', true)->has('currencies', 12));

    Transaction::factory()->create([
        'household_id' => $this->household->id,
        'budget_month_id' => $this->month->id,
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get("{$this->base}/settings")
        ->assertInertia(fn (Assert $p) => $p->where('currencyLocked', true));
});

it('changes the currency and scales the budget amounts to keep the same number', function () {
    $this->actingAs($this->owner)->put("{$this->base}/settings", ['name' => 'Our home', 'currency' => 'USD'])
        ->assertSessionHas('success', 'Household saved.');

    expect($this->household->fresh()->currency)->toBe('USD')
        ->and($this->month->budget->fresh()->default_amount)->toBe(200_000_000)
        ->and($this->month->fresh()->allocated)->toBe(200_000_000)
        ->and($this->month->fresh()->carried_in)->toBe(5_000_000);

    $this->actingAs($this->owner)
        ->put("{$this->base}/settings", ['name' => 'Our home', 'currency' => 'JPY'])
        ->assertRedirect();

    expect($this->month->fresh()->allocated)->toBe(2_000_000)->and($this->household->fresh()->currency)->toBe('JPY');
});

it('refuses to change the currency once anything has been recorded, even if deleted', function () {
    Transaction::factory()->create([
        'household_id' => $this->household->id,
        'budget_month_id' => $this->month->id,
        'user_id' => $this->owner->id,
    ])->delete();

    $this->actingAs($this->owner)
        ->put(
            "{$this->base}/settings",
            ['name' => 'Our home', 'currency' => 'USD']
        )->assertSessionHasErrors('currency');

    expect($this->household->fresh()->currency)->toBe('IDR')->and($this->month->fresh()->allocated)->toBe(2_000_000);
});

it('still lets you rename the household when the currency is locked', function () {
    Transaction::factory()->create([
        'household_id' => $this->household->id,
        'budget_month_id' => $this->month->id,
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->put("{$this->base}/settings", [
            'name' => 'Rumah kami',
            'currency' => 'IDR',
        ])->assertSessionHasNoErrors();

    expect($this->household->fresh()->name)->toBe('Rumah kami');
});

it('rejects unknown currencies and non-owners', function () {
    $member = User::factory()->create();
    $this->household->members()->attach($member->id, ['role' => 'editor']);

    $this->actingAs($this->owner)
        ->put(
            "{$this->base}/settings",
            ['name' => 'Our home', 'currency' => 'XXX']
        )->assertSessionHasErrors('currency');
    $this->actingAs($member)
        ->put("{$this->base}/settings", ['name' => 'Hacked', 'currency' => 'USD'])
        ->assertForbidden();

    expect($this->household->fresh()->name)->toBe('Our home');
});

it('shares the household currency with every page', function () {
    $this->household->update(['currency' => 'USD']);

    $this->actingAs($this->owner)->get($this->base)->assertInertia(fn (Assert $p) => $p
        ->where('currency.code', 'USD')
        ->where('currency.symbol', '$')
        ->where('currency.exponent', 2));
});

it('formats amounts for messages', function () {
    expect(Currencies::format(1_234_567, 'IDR'))->toBe('Rp 1.234.567')
        ->and(Currencies::format(1234, 'USD'))->toBe('$ 12.34')
        ->and(Currencies::format(5, 'ZZZ'))->toBe('Rp 5');
});
