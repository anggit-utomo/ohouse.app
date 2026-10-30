<?php

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdSettingsController extends Controller
{
    public function show(Request $request, Household $household): Response
    {
        return Inertia::render('settings/household', [
            'household' => ['id' => $household->id, 'name' => $household->name, 'currency' => $household->currency],
            'user' => $request->user()->only(['name', 'email']),
            'isOwner' => $request->user()->can('manage', $household),
        ]);
    }

    public function update(Request $request, Household $household): RedirectResponse
    {
        Gate::authorize('manage', $household);

        $household->update($request->validate(['name' => ['required', 'string', 'max:50']]));

        return back();
    }

    /** Deletes the household and everything in it. */
    public function destroy(Request $request, Household $household): RedirectResponse
    {
        Gate::authorize('manage', $household);

        $request->validate(['confirm' => ['required', 'string']]);

        if ($request->input('confirm') !== $household->name) {
            throw ValidationException::withMessages(['confirm' => 'Type the household name exactly to delete it.']);
        }

        $household->delete();

        return redirect()->route('dashboard');
    }
}
