<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpenHouseholdSettings extends Controller
{
    public function profile(Request $request)
    {
        return $this->householdSettings($request) ?? app()->call([ProfileController::class, 'edit']);
    }

    public function appearance(Request $request)
    {
        return $this->householdSettings($request) ?? Inertia::render('settings/appearance');
    }

    private function householdSettings(Request $request): ?RedirectResponse
    {
        $user = $request->user();

        $household = $user->households()->whereKey($user->current_household_id)->first()
            ?? $user->households()->first();

        return $household ? redirect()->route('household.settings', $household) : null;
    }
}
