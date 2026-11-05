<?php

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Http\RedirectResponse;

class OpenHouseholdSettings extends Controller
{
    public function profile(Household $household): RedirectResponse
    {
        return redirect()->route('household.settings', $household);
    }

    public function appearance(Household $household): RedirectResponse
    {
        return redirect()->route('household.settings', $household);
    }
}
