<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create(['name' => 'Our home', 'period_start' => '2026-10-01']);
    $this->owner = $this->household->owner;
    $this->profile = "/h/{$this->household->id}/settings/profile";
    $this->month = BudgetMonth::factory()->for(Budget::factory()->for($this->household)->create())
        ->create(['period_start' => '2026-10-01', 'allocated' => 1_000_000]);
});

function acMember(Household $h, string $role = 'editor'): User
{
    $user = User::factory()->create();
    $h->members()->attach($user->id, ['role' => $role]);

    return $user;
}

it('sends people in a household to the household settings page', function () {
    $settings = "/h/{$this->household->id}/settings";

    $this->actingAs($this->owner)->get($this->profile)->assertRedirect($settings);
    $this->actingAs($this->owner)->get("{$settings}/appearance")->assertRedirect($settings);
    $this->actingAs($this->owner)->get('/settings')->assertNotFound();
});

it('hides scoped settings from people outside the household', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get($this->profile)->assertNotFound();
    $this->actingAs($user)->get("/h/{$this->household->id}/settings/appearance")->assertNotFound();
});

it('updates your name, and asks to verify a new email address', function () {
    Notification::fake();

    $this->actingAs($this->owner)->patch($this->profile, ['name' => 'New Name', 'email' => $this->owner->email])
        ->assertSessionHas('success', 'Profile saved.');

    expect($this->owner->fresh()->name)->toBe('New Name');
    Notification::assertNothingSent();

    $this->actingAs($this->owner)
        ->patch(
            $this->profile,
            ['name' => 'New Name', 'email' => 'new@example.com']
        )->assertSessionHasNoErrors();

    expect($this->owner->fresh())->email->toBe('new@example.com')->email_verified_at->toBeNull();
    Notification::assertSentTo($this->owner->fresh(), VerifyEmail::class);
});

it('rejects an email that is taken or invalid', function () {
    $other = User::factory()->create();

    $this->actingAs($this->owner)
        ->patch(
            $this->profile,
            ['name' => 'X', 'email' => $other->email]
        )->assertSessionHasErrors('email');
    $this->actingAs($this->owner)
        ->patch(
            $this->profile,
            ['name' => '', 'email' => 'nope']
        )->assertSessionHasErrors(['name', 'email']);
});

it('does not let an owner delete the account, which would delete the household', function () {
    $this->actingAs($this->owner)
        ->delete(
            $this->profile,
            ['password' => 'password']
        )->assertSessionHasErrors('password');

    expect(User::find($this->owner->id))
        ->not->toBeNull()
        ->and(Household::find($this->household->id))
        ->not->toBeNull();
});

it('needs the right password to delete an account', function () {
    $member = acMember($this->household);

    $this->actingAs($member)
        ->delete(
            $this->profile,
            ['password' => 'wrong']
        )->assertSessionHasErrors('password');

    expect(User::find($member->id))->not->toBeNull();
});

it('deletes a member who has no entries', function () {
    $member = acMember($this->household);

    $this->actingAs($member)->delete($this->profile, ['password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/');

    expect(User::find($member->id))->toBeNull()->and($this->household->members()->count())->toBe(1);
});

it('anonymises a member who has entries and keeps the household history', function () {
    $member = acMember($this->household);
    $member->forceFill(['current_household_id' => $this->household->id])->save();
    $entry = Transaction::factory()->create([
        'household_id' => $this->household->id,
        'budget_month_id' => $this->month->id,
        'user_id' => $member->id,
        'amount' => 75_000,
    ]);

    $this->actingAs($member)->delete($this->profile, ['password' => 'password'])->assertRedirect('/');

    $member = $member->fresh();

    expect($member)->name->toBe('Former member')
        ->email->toBe("deleted-{$member->id}@deleted.invalid")
        ->current_household_id->toBeNull()
        ->and($member->households()->count())->toBe(0)
        ->and($entry->fresh()->amount)->toBe(75_000)
        ->and(Household::find($this->household->id))->not->toBeNull();

    $this->assertGuest();
});

it('does not expose account deletion to someone outside a household', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete($this->profile, ['password' => 'password'])->assertNotFound();

    expect(User::find($user->id))->not->toBeNull();
});
