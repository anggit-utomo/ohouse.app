<?php

namespace App\Listeners;

use Illuminate\Contracts\Events\ShouldBeDiscovered;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\SsrRenderFailed;

class LogSsrRenderFailure implements ShouldBeDiscovered
{
    /**
     * Keep the listener out of event discovery: it is registered explicitly in
     * AppServiceProvider so a renderer outage always leaves a log line.
     */
    public static function shouldBeDiscovered(): bool
    {
        return false;
    }

    public function handle(SsrRenderFailed $event): void
    {
        $context = $event->toArray();

        if ($event->stack) {
            $context['stack'] = $event->stack;
        }

        Log::warning('Inertia SSR render failed; serving the client-only response.', $context);
    }
}
