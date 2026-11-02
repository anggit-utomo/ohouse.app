<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\OpenHouseholdSettings;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Profile
Route::middleware(['auth', 'household.member'])
    ->prefix('h/{household}')
    ->scopeBindings()
    ->group(function () {
        Route::get('settings/profile', [OpenHouseholdSettings::class, 'profile'])
            ->name('profile.edit');

        Route::patch('settings/profile', [AccountController::class, 'update'])
            ->name('profile.update');
    });

// Verified household settings
Route::middleware(['auth', 'verified', 'household.member'])
    ->prefix('h/{household}')
    ->scopeBindings()
    ->group(function () {
        Route::delete('settings/profile', [AccountController::class, 'destroy'])
            ->name('profile.destroy');

        Route::get('settings/security', [SecurityController::class, 'edit'])
            ->middleware(RequirePassword::class)
            ->name('security.edit');

        Route::put('settings/password', [SecurityController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('user-password.update');

        Route::get('settings/appearance', [OpenHouseholdSettings::class, 'appearance'])
            ->name('appearance.edit');
    });

// Passkey discovery
Route::middleware('auth')->get('.well-known/passkey-endpoints', function () {
    $user = request()->user();

    $household = $user->households()
        ->whereKey($user->current_household_id)
        ->first() ?? $user->households()->first();

    abort_unless($household, 404);

    return response()->json([
        'enroll' => route('security.edit', $household),
        'manage' => route('security.edit', $household),
    ]);
})->name('well-known.passkeys');
