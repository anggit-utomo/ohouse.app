<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($data);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', $emailChanged ? 'Profile saved. Check your email to verify the new address.' : 'Profile saved.');
    }

    /**
     * Owners must delete the household first, because deleting an owner would delete it for everyone.
     * Anyone whose name is on recorded transactions is anonymised instead of deleted,
     * so the household's history stays whole.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        if (Household::where('owner_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['password' => 'You own a household. Delete the household first, then delete your account.']);
        }

        DB::transaction(function () use ($user) {
            $user->households()->detach();

            if ($user->transactions()->exists()) {
                $user->forceFill([
                    'name' => 'Former member',
                    'email' => "deleted-{$user->id}@deleted.invalid",
                    'email_verified_at' => null,
                    'password' => Hash::make(Str::random(64)),
                    'remember_token' => null,
                    'current_household_id' => null,
                    // drop these three lines if your users table has no two-factor columns
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->save();
            } else {
                $user->delete();
            }
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
