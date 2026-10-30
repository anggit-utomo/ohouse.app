<?php

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('serves an installable manifest', function () {
    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/dashboard')
        ->assertJsonPath('icons.0.src', '/icons/icon-192.png');
});

it('shows the setup page to people without a household', function () {
    $this->actingAs(User::factory()->create())->get('/setup')
        ->assertInertia(fn (Assert $p) => $p->component('setup/create')->has('suggestions', 6));
});

it('creates the household with its starter budgets and sends you to set amounts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/setup', ['name' => 'Our home', 'budgets' => ['Food', 'Gasoline']]);

    $household = Household::first();
    $response->assertRedirect("/h/{$household->id}/budgets");

    expect($household->name)->toBe('Our home')
        ->and($user->roleIn($household)->value)->toBe('owner')
        ->and($user->fresh()->current_household_id)->toBe($household->id)
        ->and($household->budgets()->count())->toBe(2)
        ->and($household->budgets()->first()->default_amount)->toBe(0)
        ->and($household->budgets()->first()->months()->count())->toBe(1);
});

it('validates the setup form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/setup', ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs($user)->post('/setup', ['name' => 'Home', 'budgets' => ['Food', 'Food']])
        ->assertSessionHasErrors('budgets.0');
    $this->actingAs($user)->post('/setup', ['name' => 'Home', 'budgets' => array_fill(0, 13, 'x')])
        ->assertSessionHasErrors('budgets');

    expect(Household::count())->toBe(0);
});

it('does not set up a second household', function () {
    $household = Household::factory()->create();

    $this->actingAs($household->owner)->get('/setup')->assertRedirect('/dashboard');
    $this->actingAs($household->owner)->post('/setup', ['name' => 'Another'])->assertRedirect('/dashboard');

    expect(Household::count())->toBe(1);
});

it('sends a newcomer with a remembered QR code to the join page', function () {
    $this->actingAs(User::factory()->create())->withSession(['join_code' => 'KTR-4829'])
        ->get('/dashboard')
        ->assertRedirect('/join');
});

it('shows the remembered code on the join page and forgets it after joining', function () {
    $household = Household::factory()->create();
    HouseholdInvite::create([
        'household_id' => $household->id,
        'code' => 'KTR-4829',
        'created_by' => $household->owner_id,
        'expires_at' => now()->addMinutes(10),
    ]);
    $guest = User::factory()->create();

    $this->actingAs($guest)->withSession(['join_code' => 'KTR-4829'])->get('/join')
        ->assertInertia(fn (Assert $p) => $p->where('code', 'KTR-4829')->where('preview.members', 1));

    $this->actingAs($guest)->withSession(['join_code' => 'KTR-4829'])->post('/join', ['code' => 'KTR-4829'])
        ->assertRedirect("/h/{$household->id}")->assertSessionMissing('join_code');
});

it('lets viewers see the pages but flags that they cannot add', function () {
    $household = Household::factory()->create();
    $viewer = User::factory()->create();
    $household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("/h/{$household->id}/transactions")
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
    $this->actingAs($viewer)->get("/h/{$household->id}/family")
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
    $this->actingAs($household->owner)->get("/h/{$household->id}/family")
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', true));
});
