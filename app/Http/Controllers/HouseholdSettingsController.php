<?php

namespace App\Http\Controllers;

use App\Actions\ChangeCurrency;
use App\Models\Household;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdSettingsController extends Controller
{
    public function show(Request $request, Household $household): Response
    {
        $user = $request->user();

        return Inertia::render('settings/household', [
            'household' => ['id' => $household->id, 'name' => $household->name, 'currency' => $household->currency],
            'currencies' => Currencies::all(),
            'currencyLocked' => $household->transactions()->withTrashed()->exists(),
            'user' => ['name' => $user->name, 'email' => $user->email, 'verified' => $user->hasVerifiedEmail()],
            'isOwner' => $user->can('manage', $household),
            'ownsHousehold' => Household::where('owner_id', $user->id)->exists(),
        ]);
    }

    public function update(Request $request, Household $household, ChangeCurrency $change): RedirectResponse
    {
        $this->authorize('manage', $household);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'currency' => ['required', Rule::in(Currencies::codes())],
        ]);

        if ($data['currency'] !== $household->currency) {
            $change->handle($household, $data['currency']);
        }

        $household->update(['name' => $data['name']]);

        return back()->with('success', 'Household saved.');
    }

    /** Deletes the household and everything in it. */
    public function destroy(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('manage', $household);

        $request->validate(['confirm' => ['required', 'string']]);

        if ($request->input('confirm') !== $household->name) {
            throw ValidationException::withMessages(['confirm' => 'Type the household name exactly to delete it.']);
        }

        $household->delete();

        return redirect()->route('dashboard')->with('success', 'Household deleted.');
    }
}
