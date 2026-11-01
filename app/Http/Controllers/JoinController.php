<?php

namespace App\Http\Controllers;

use App\Actions\JoinHousehold;
use App\Models\HouseholdInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    /** The QR code points here. */
    public function show(string $code): RedirectResponse
    {
        $code = HouseholdInvite::normalize($code);

        session(['join_code' => $code]);

        if (! auth()->check()) {
            return redirect()->route('join.create');
        }

        return redirect()->route('join.create', ['code' => $code]);
    }

    public function create(Request $request): Response
    {
        $code = (string) ($request->query('code') ?: session('join_code', ''));
        $invite = $code !== '' ? HouseholdInvite::findActive($code) : null;

        return Inertia::render('join/create', [
            'code' => $invite?->code ?? $code,
            'preview' => $invite ? [
                'name' => $invite->household->name,
                'budgets' => $invite->household->budgets()->active()->count(),
                'members' => $invite->household->members()->count(),
            ] : null,
            'invalid' => $code !== '' && ! $invite,
        ]);
    }

    public function store(Request $request, JoinHousehold $join): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        $household = $join->handle($request->user(), $data['code']);
        session()->forget('join_code');

        return redirect()->route('households.home', $household)
            ->with('success', 'You joined the household.');
    }
}
