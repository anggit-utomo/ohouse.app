<?php

use Illuminate\Support\Facades\Route;

// Passkey discovery
Route::middleware('auth')->get('.well-known/passkey-endpoints', function () {
    $user = request()->user();

    $household = $user->households()
        ->whereKey($user->current_household_id)
        ->first() ?? $user->households()->first();

    abort_if($household === null, 404);

    return response()->json([
        'enroll' => route('security.edit', $household),
        'manage' => route('security.edit', $household),
    ]);
})->name('well-known.passkeys');
