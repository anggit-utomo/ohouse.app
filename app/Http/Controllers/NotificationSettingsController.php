<?php

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function update(Request $request, Household $household): RedirectResponse
    {
        $data = $request->validate([
            'notify' => ['required', 'boolean'],
            'notify_amounts' => ['required', 'boolean'],
        ]);

        $household->members()->updateExistingPivot($request->user()->id, $data);

        return back()->with('success', 'Notification settings saved.');
    }
}
