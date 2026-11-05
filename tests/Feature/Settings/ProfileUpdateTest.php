<?php

use App\Models\Household;
use App\Models\User;

test('profile page is displayed', function () {
    $household = Household::factory()->create();
    $response = $this
        ->actingAs($household->owner)
        ->get(route('profile.edit', $household));

    $response->assertRedirect(route('household.settings', $household));
});

test('profile information can be updated', function () {
    $household = Household::factory()->create();
    $user = $household->owner;

    $response = $this
        ->actingAs($user)
        ->from(route('household.settings', $household))
        ->patch(route('profile.update', $household), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('household.settings', $household));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $household = Household::factory()->create();
    $user = $household->owner;

    $response = $this
        ->actingAs($user)
        ->from(route('household.settings', $household))
        ->patch(route('profile.update', $household), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('household.settings', $household));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create();
    $household->members()->attach($user->id, ['role' => 'editor']);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy', $household), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    $household = Household::factory()->create();
    $household->members()->attach($user->id, ['role' => 'editor']);

    $response = $this
        ->actingAs($user)
        ->from(route('household.settings', $household))
        ->delete(route('profile.destroy', $household), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('household.settings', $household));

    expect($user->fresh())->not->toBeNull();
});
