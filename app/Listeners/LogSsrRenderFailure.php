<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Inertia\Ssr\SsrRenderFailed;

class LogSsrRenderFailure
{
    public function handle(SsrRenderFailed $event): void
    {
        $context = $event->toArray();

        if ($event->stack) {
            $context['stack'] = $event->stack;
        }

        Log::warning('Inertia SSR render failed; serving the client-only response.', $context);
    }
}
