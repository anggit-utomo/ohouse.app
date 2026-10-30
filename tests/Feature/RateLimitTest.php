<?php

use App\Models\Household;
use App\Models\User;

it('limits how fast pair codes can be created', function () {
    $household = Household::factory()->create();

    foreach (range(1, 6) as $i) {
        $this->actingAs($household->owner)->post("/h/{$household->id}/family/invite")->assertRedirect();
    }

    $this->actingAs($household->owner)->post("/h/{$household->id}/family/invite")->assertStatus(429);
});

it('locks sign-in after repeated wrong passwords', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
});

it('limits how fast join codes can be guessed', function () {
    $user = User::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user)->post('/join', ['code' => 'NOP-0000']);
    }

    $this->actingAs($user)->post('/join', ['code' => 'NOP-0000'])->assertStatus(429);
});
