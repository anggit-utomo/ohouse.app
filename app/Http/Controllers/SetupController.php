<?php

namespace App\Http\Controllers;

use App\Actions\CreateHousehold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->households()->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('setup/create', [
            'suggestions' => ['Food', 'Internet', 'Kitchen items', 'Gasoline', 'Transport', 'Savings'],
        ]);
    }

    public function store(Request $request, CreateHousehold $create): RedirectResponse
    {
        $user = $request->user();

        if ($user->households()->exists()) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'budgets' => ['array', 'max:12'],
            'budgets.*' => ['string', 'max:50', 'distinct'],
        ]);

        $household = $create->handle($user, $data['name'], 'IDR', 1, $data['budgets'] ?? []);

        $user->forceFill([
            'current_household_id' => $household->id,
        ])->save();

        return redirect()->route('budgets.edit', $household)
            ->with('success', 'Household created successfully.');
    }
}
