<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\HouseholdInvite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    public function show(Request $request, Household $household): Response
    {
        $user = $request->user();
        $canManage = $user->can('manage', $household);
        $invite = $canManage ? $household->invites()->active()->latest('id')->first() : null;

        return Inertia::render('family/show', [
            'household' => ['id' => $household->id],
            'canManage' => $canManage,
            'invite' => $invite ? [
                'code' => $invite->code,
                'url' => url('/join/'.$invite->code),
                'expires_at' => $invite->expires_at->toIso8601String(),
            ] : null,
            'members' => $household->members()->orderBy('household_user.joined_at')->get()->map(fn (User $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'role' => $m->pivot->role,
                'you' => $m->id === $user->id,
                'joined_at' => Carbon::parse($m->pivot->joined_at)->toIso8601String(),
            ])->values(),
            'canEdit' => $request->user()->can('edit', $household),
        ]);
    }

    /** Makes a fresh code and revokes any older one. */
    public function invite(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('manage', $household);

        HouseholdInvite::generate($household, $request->user());

        return back()->with('success', 'New pair code created.');
    }

    public function update(Request $request, Household $household, int $member): RedirectResponse
    {
        $this->authorize('manage', $household);

        $data = $request->validate(['role' => ['required', 'in:editor,viewer']]);
        $target = $household->members()->whereKey($member)->firstOrFail();

        if ($target->pivot->role === 'owner') {
            throw ValidationException::withMessages(['role' => 'The owner\'s role can\'t be changed.']);
        }

        $household->members()->updateExistingPivot($target->id, ['role' => $data['role']]);

        return back()->with('success', 'New pair code created.');
    }

    /** The owner removes someone, or anyone but the owner leaves on their own. */
    public function destroy(Request $request, Household $household, int $member): RedirectResponse
    {
        $target = $household->members()->whereKey($member)->firstOrFail();
        $leaving = $target->id === $request->user()->id;

        if (! $leaving) {
            $this->authorize('manage', $household);
        }

        if ($target->pivot->role === 'owner') {
            throw ValidationException::withMessages(['member' => 'The owner can\'t be removed. Delete the household instead.']);
        }

        $household->members()->detach($target->id);
        User::whereKey($target->id)->where('current_household_id', $household->id)->update(['current_household_id' => null]);

        return $leaving
            ? redirect()->route('dashboard')->with('success', 'You left the household.')
            : back()->with('success', 'Member removed.');
    }
}
