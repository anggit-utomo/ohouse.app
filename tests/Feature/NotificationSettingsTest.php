<?php

use App\Models\Household;
use App\Models\User;

it('saves the signed-in member notification preferences', function () {
    $household = Household::factory()->create();
    $member = $household->owner;

    $this->actingAs($member)
        ->patch("/h/{$household->id}/settings/notifications", [
            'notify' => false,
            'notify_amounts' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Notification settings saved.');

    $membership = $household->members()->whereKey($member->id)->firstOrFail()->pivot;

    expect((bool) $membership->notify)->toBeFalse()
        ->and((bool) $membership->notify_amounts)->toBeTrue();
});

it('rejects invalid or missing notification preferences without changing them', function () {
    $household = Household::factory()->create();
    $member = $household->owner;

    $this->actingAs($member)
        ->patch("/h/{$household->id}/settings/notifications", [
            'notify' => 'sometimes',
        ])
        ->assertSessionHasErrors(['notify', 'notify_amounts']);

    $membership = $household->members()->whereKey($member->id)->firstOrFail()->pivot;

    expect((bool) $membership->notify)->toBeTrue()
        ->and((bool) $membership->notify_amounts)->toBeTrue();
});

it('hides notification preferences from users outside the household', function () {
    $household = Household::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->patch("/h/{$household->id}/settings/notifications", [
            'notify' => false,
            'notify_amounts' => false,
        ])
        ->assertNotFound();
});

it('requires authentication to update notification preferences', function () {
    $household = Household::factory()->create();

    $this->patch("/h/{$household->id}/settings/notifications", [
        'notify' => false,
        'notify_amounts' => false,
    ])->assertRedirect('/login');
});
