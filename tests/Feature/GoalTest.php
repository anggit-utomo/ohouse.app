<?php

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->url = "/h/{$this->household->id}";
});

it('creates a goal and shows it on Home and the Goals screen', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals", [
        'name' => 'Bali',
        'target_amount' => 6_000_000,
        'target_date' => '2027-06-01',
    ])->assertRedirect();

    $goal = Goal::sole();

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/index')
            ->has('goals', 1)
            ->where('goals.0.name', 'Bali')
            ->where('goals.0.target', 6_000_000)
            ->where('goals.0.balance', 0)
            ->where('goals.0.pct', 0)
            ->where('saved', 0)
            ->where('canEdit', true));

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p
            ->has('goals', 1)
            ->where('goals.0.name', 'Bali')
            ->where('saved', 0));

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$goal->id}")
        ->assertInertia(fn (Assert $p) => $p
            ->component('goals/show')
            ->where('goal.name', 'Bali')
            ->where('goal.target_date', '2027-06-01'));
});

it('requires a name and a target amount', function () {
    $this->actingAs($this->owner)->post("{$this->url}/goals", [
        'name' => '',
        'target_amount' => 0,
    ])->assertSessionHasErrors(['name', 'target_amount']);

    expect(Goal::count())->toBe(0);
});

it('renames a goal and changes its target', function () {
    $goal = Goal::factory()->for($this->household)->create([
        'name' => 'Bali',
        'target_amount' => 6_000_000,
    ]);

    $this->actingAs($this->owner)->patch("{$this->url}/goals/{$goal->id}", [
        'name' => 'Japan',
        'target_amount' => 9_000_000,
        'target_date' => '2027-08-01',
        'sort' => 5,
    ])->assertRedirect();

    expect($goal->fresh()->name)->toBe('Japan')
        ->and($goal->fresh()->target_amount)->toBe(9_000_000)
        ->and($goal->fresh()->target_date->toDateString())->toBe('2027-08-01')
        ->and($goal->fresh()->sort)->toBe(5);
});

it('lets an editor create a goal', function () {
    $editor = User::factory()->create();
    $this->household->members()->attach($editor->id, ['role' => 'editor']);

    $this->actingAs($editor)->post("{$this->url}/goals", [
        'name' => 'Laptop',
        'target_amount' => 2_000_000,
    ])->assertRedirect();

    expect(Goal::sole()->name)->toBe('Laptop');
});

it('lists goals in their saved order', function () {
    Goal::factory()->for($this->household)->create(['name' => 'Second', 'sort' => 2]);
    Goal::factory()->for($this->household)->create(['name' => 'First', 'sort' => 1]);

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p
            ->where('goals.0.name', 'First')
            ->where('goals.1.name', 'Second'));
});

it('hides archived goals', function () {
    Goal::factory()->for($this->household)->create(['archived_at' => now()]);

    $this->actingAs($this->owner)->get($this->url)
        ->assertInertia(fn (Assert $p) => $p->has('goals', 0)->where('saved', 0));

    $this->actingAs($this->owner)->get("{$this->url}/goals")
        ->assertInertia(fn (Assert $p) => $p->has('goals', 0));
});

it('lets viewers read goals but not change them', function () {
    $goal = Goal::factory()->for($this->household)->create();
    $viewer = User::factory()->create();
    $this->household->members()->attach($viewer->id, ['role' => 'viewer']);

    $this->actingAs($viewer)->get("{$this->url}/goals")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
    $this->actingAs($viewer)->get("{$this->url}/goals/{$goal->id}")->assertOk();

    $this->actingAs($viewer)->post("{$this->url}/goals", [
        'name' => 'Nope',
        'target_amount' => 1_000,
    ])->assertForbidden();

    $this->actingAs($viewer)->patch("{$this->url}/goals/{$goal->id}", [
        'name' => 'Nope',
        'target_amount' => 1_000,
    ])->assertForbidden();
});

it('hides goals from outsiders', function () {
    $goal = Goal::factory()->for($this->household)->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("{$this->url}/goals")->assertNotFound();
    $this->actingAs($outsider)->get("{$this->url}/goals/{$goal->id}")->assertNotFound();
});

it('does not open a goal from another household', function () {
    $goal = Goal::factory()->for(Household::factory())->create();

    $this->actingAs($this->owner)->get("{$this->url}/goals/{$goal->id}")->assertNotFound();
});
