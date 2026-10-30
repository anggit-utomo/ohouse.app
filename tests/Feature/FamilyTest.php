<?php

use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
    $this->household = Household::factory()->create();
    $this->owner = $this->household->owner;
    $this->base = "/h/{$this->household->id}";
    $this->guest = User::factory()->create();
});

function famInvite(Household $h, string $code = 'KTR-4829', int $minutes = 10, array $over = []): HouseholdInvite
{
    return HouseholdInvite::create(array_merge([
        'household_id' => $h->id,
        'code' => $code,
        'created_by' => $h->owner_id,
        'expires_at' => now()->addMinutes($minutes),
    ], $over));
}

function famMember(Household $h, string $role = 'editor'): User
{
    $user = User::factory()->create();
    $h->members()->attach($user->id, ['role' => $role]);

    return $user;
}

it('joins with a code as an editor and uses the code up', function () {
    $invite = famInvite($this->household);

    $this->actingAs($this->guest)->post('/join', ['code' => 'KTR-4829'])->assertRedirect($this->base);

    expect($this->guest->roleIn($this->household)?->value)->toBe('editor')
        ->and($invite->fresh())->used_at->not->toBeNull()->used_by->toBe($this->guest->id)
        ->and($this->guest->fresh()->current_household_id)->toBe($this->household->id);

    $this->actingAs(User::factory()->create())->post('/join', ['code' => 'KTR-4829'])->assertSessionHasErrors('code');
});

it('accepts the code in lower case or without the dash', function () {
    famInvite($this->household);

    $this->actingAs($this->guest)->post('/join', ['code' => ' ktr 4829 '])->assertRedirect($this->base);
});

it('rejects wrong, expired and revoked codes', function () {
    famInvite($this->household, 'OLD-1111', -1);
    famInvite($this->household, 'REV-2222', 10, ['revoked_at' => now()]);

    foreach (['NOP-0000', 'OLD-1111', 'REV-2222', 'garbage'] as $code) {
        $this->actingAs($this->guest)->post('/join', ['code' => $code])->assertSessionHasErrors('code');
    }

    expect($this->guest->households()->count())->toBe(0);
});

it('does not use up the code for someone who is already a member', function () {
    $invite = famInvite($this->household);

    $this->actingAs($this->owner)->post('/join', ['code' => 'KTR-4829'])->assertRedirect($this->base);

    expect($invite->fresh()->used_at)->toBeNull()->and($this->household->members()->count())->toBe(1);
});

it('previews the household for a valid code only', function () {
    Budget::factory()->for($this->household)->count(2)->create();
    famInvite($this->household);

    $this->actingAs($this->guest)->get('/join?code=ktr4829')->assertInertia(fn (Assert $p) => $p
        ->component('join/create')
        ->where('code', 'KTR-4829')
        ->where('preview.budgets', 2)
        ->where('preview.members', 1)
        ->where('invalid', false));

    $this->actingAs($this->guest)->get('/join?code=NOP-0000')
        ->assertInertia(fn (Assert $p) => $p->where('preview', null)->where('invalid', true));
});

it('sends the QR link to the join page and asks guests to sign in first', function () {
    $this->actingAs($this->guest)->get('/join/KTR-4829')->assertRedirect('/join?code=KTR-4829');

    auth()->logout();

    $this->get('/join')->assertRedirect('/login');
});

it('limits how fast codes can be tried', function () {
    foreach (range(1, 10) as $i) {
        $this->actingAs($this->guest)->post('/join', ['code' => 'NOP-0000']);
    }

    $this->actingAs($this->guest)->post('/join', ['code' => 'NOP-0000'])->assertStatus(429);
});

it('shows the owner the code and members, and other members only the members', function () {
    $editor = famMember($this->household);
    famInvite($this->household);

    $this->actingAs($this->owner)->get("{$this->base}/family")->assertInertia(fn (Assert $p) => $p
        ->component('family/show')->where('canManage', true)->where('invite.code', 'KTR-4829')
        ->where('invite.url', url('/join/KTR-4829'))->has('members', 2)->where('members.0.you', true));

    $this->actingAs($editor)->get("{$this->base}/family")->assertInertia(fn (Assert $p) => $p->where('canManage', false)->where('invite', null)->has('members', 2));
});

it('creates a new code and revokes the old one', function () {
    $old = famInvite($this->household);

    $this->actingAs($this->owner)->post("{$this->base}/family/invite")->assertRedirect();

    expect($old->fresh()->revoked_at)->not->toBeNull()
        ->and($this->household->invites()->active()->count())->toBe(1)
        ->and($this->household->invites()->active()->first()->code)->not->toBe('KTR-4829');
});

it('only the owner makes codes and changes roles', function () {
    $editor = famMember($this->household);
    $other = famMember($this->household);

    $this->actingAs($editor)->post("{$this->base}/family/invite")->assertForbidden();
    $this->actingAs($editor)->patch("{$this->base}/family/members/{$other->id}", ['role' => 'viewer'])->assertForbidden();
    $this->actingAs($editor)->delete("{$this->base}/family/members/{$other->id}")->assertForbidden();
});

it('lets the owner change a role but not the owner\'s', function () {
    $editor = famMember($this->household);

    $this->actingAs($this->owner)->patch("{$this->base}/family/members/{$editor->id}", ['role' => 'viewer'])->assertRedirect();
    expect($editor->roleIn($this->household)->value)->toBe('viewer');

    $this->actingAs($this->owner)->patch("{$this->base}/family/members/{$this->owner->id}", ['role' => 'viewer'])->assertSessionHasErrors('role');
    $this->actingAs($this->owner)->patch("{$this->base}/family/members/{$editor->id}", ['role' => 'owner'])->assertSessionHasErrors('role');
});

it('removes a member, keeps their transactions and shuts them out', function () {
    $editor = famMember($this->household);
    $editor->forceFill(['current_household_id' => $this->household->id])->save();
    $month = BudgetMonth::factory()->for(Budget::factory()->for($this->household))->create(['period_start' => '2026-10-01']);
    Transaction::factory()->create(['household_id' => $this->household->id, 'budget_month_id' => $month->id, 'user_id' => $editor->id]);

    $this->actingAs($this->owner)->delete("{$this->base}/family/members/{$editor->id}")->assertRedirect();

    expect($editor->households()->count())->toBe(0)->and($editor->fresh()->current_household_id)->toBeNull()
        ->and(Transaction::where('user_id', $editor->id)->count())->toBe(1);

    $this->actingAs($editor)->get($this->base)->assertNotFound();
});

it('lets a member leave but never the owner', function () {
    $editor = famMember($this->household);

    $this->actingAs($editor)->delete("{$this->base}/family/members/{$editor->id}")->assertRedirect(route('dashboard'));
    expect($editor->households()->count())->toBe(0);

    $this->actingAs($this->owner)->delete("{$this->base}/family/members/{$this->owner->id}")->assertSessionHasErrors('member');
});

it('lets the dashboard cope with a household you left', function () {
    $mine = Household::factory()->create(['owner_id' => $this->guest->id]);
    $this->guest->forceFill(['current_household_id' => $this->household->id])->save();

    $this->actingAs($this->guest)->get('/dashboard')->assertRedirect("/h/{$mine->id}");
});

it('shows settings to members and renames only for the owner', function () {
    $editor = famMember($this->household);

    $this->actingAs($editor)->get("{$this->base}/settings")->assertInertia(fn (Assert $p) => $p->component('settings/household')->where('isOwner', false));
    $this->actingAs($editor)->put("{$this->base}/settings", ['name' => 'Hacked'])->assertForbidden();

    $this->actingAs($this->owner)->put("{$this->base}/settings", ['name' => 'Our home'])->assertRedirect();
    expect($this->household->fresh()->name)->toBe('Our home');

    $this->actingAs($this->owner)->put("{$this->base}/settings", ['name' => ''])->assertSessionHasErrors('name');
});

it('deletes the household only with the exact name and only for the owner', function () {
    $this->household->update(['name' => 'Our home']);
    $editor = famMember($this->household);
    $month = BudgetMonth::factory()->for(Budget::factory()->for($this->household))->create(['period_start' => '2026-10-01']);
    Transaction::factory()->create(['household_id' => $this->household->id, 'budget_month_id' => $month->id]);

    $this->actingAs($editor)->delete("{$this->base}/settings", ['confirm' => 'Our home'])->assertForbidden();
    $this->actingAs($this->owner)->delete("{$this->base}/settings", ['confirm' => 'our house'])->assertSessionHasErrors('confirm');
    expect(Household::count())->toBe(1);

    $this->actingAs($this->owner)->delete("{$this->base}/settings", ['confirm' => 'Our home'])->assertRedirect(route('dashboard'));

    expect(Household::count())->toBe(0)->and(Budget::count())->toBe(0)->and(Transaction::count())->toBe(0);
});

it('hides family and settings from outsiders', function () {
    $this->actingAs($this->guest)->get("{$this->base}/family")->assertNotFound();
    $this->actingAs($this->guest)->get("{$this->base}/settings")->assertNotFound();
    $this->actingAs($this->guest)->post("{$this->base}/family/invite")->assertNotFound();
});

it('remembers the QR code, then makes guests sign in', function () {
    $this->get('/join/ktr-4829')->assertRedirect('/join')->assertSessionHas('join_code', 'KTR-4829');
    $this->get('/join')->assertRedirect('/login');
});
