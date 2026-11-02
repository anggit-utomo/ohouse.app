<?php

namespace App\Http\Controllers;

use App\Notifications\EntryAdded;
use App\Support\PushPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'regex:/^https:\/\//'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $subscription = $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aesgcm',
        );

        $subscription->forceFill(['device' => $this->device($request->userAgent())])->save();

        return back()->with('success', 'Notifications are on for this device.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:1000']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return back()->with('success', 'Notifications are off for this device.');
    }

    public function remove(Request $request, int $id): RedirectResponse
    {
        $request->user()->pushSubscriptions()->whereKey($id)->delete();

        return back()->with('success', 'Device removed.');
    }

    public function test(Request $request): RedirectResponse
    {
        $request->user()->notify(new EntryAdded(new PushPayload(
            title: 'Notifications are working',
            body: 'This is a test.',
            url: '/dashboard',
            tag: 'test-'.now()->timestamp,
        )));

        return back()->with('success', 'Test sent. It should arrive in a few seconds.');
    }

    private function device(?string $agent): string
    {
        $agent = (string) $agent;

        $os = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return "{$os} · {$browser}";
    }
}
