<?php

use App\Models\User;
use App\Notifications\EntryAdded;
use Illuminate\Support\Facades\Notification;

function pushSubscription(User $user, string $endpoint): void
{
    $user->updatePushSubscription($endpoint, 'public-key', 'auth-token', 'aes128gcm');
}

it('stores a valid subscription and labels its device', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) Version/17.0 Safari/605.1')
        ->post('/push/subscribe', [
            'endpoint' => 'https://push.example.test/subscription/one',
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Notifications are on for this device.');

    $subscription = $user->pushSubscriptions()->firstOrFail();

    expect($subscription->endpoint)->toBe('https://push.example.test/subscription/one')
        ->and($subscription->content_encoding->value)->toBe('aesgcm')
        ->and($subscription->device)->toBe('iPhone · Safari');
});

it('removes a subscription by endpoint', function () {
    $user = User::factory()->create();
    pushSubscription($user, 'https://push.example.test/subscription/remove');

    $this->actingAs($user)
        ->delete('/push/subscribe', [
            'endpoint' => 'https://push.example.test/subscription/remove',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Notifications are off for this device.');

    expect($user->pushSubscriptions()->exists())->toBeFalse();
});

it('only removes a device belonging to the signed-in user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    pushSubscription($user, 'https://push.example.test/subscription/mine');
    pushSubscription($other, 'https://push.example.test/subscription/other');
    $otherDevice = $other->pushSubscriptions()->firstOrFail();

    $this->actingAs($user)
        ->delete("/push/devices/{$otherDevice->id}")
        ->assertRedirect();

    expect($user->pushSubscriptions()->count())->toBe(1)
        ->and($other->pushSubscriptions()->count())->toBe(1);
});

it('rejects an invalid subscription payload', function () {
    $this->actingAs(User::factory()->create())
        ->post('/push/subscribe', [
            'endpoint' => 'http://push.example.test/subscription',
        ])
        ->assertSessionHasErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

it('sends a test notification to the signed-in user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/push/test')
        ->assertRedirect()
        ->assertSessionHas('success', 'Test sent. It should arrive in a few seconds.');

    Notification::assertSentTo($user, EntryAdded::class);
});

it('requires authentication to manage push subscriptions', function () {
    $this->post('/push/test')->assertRedirect('/login');
});
