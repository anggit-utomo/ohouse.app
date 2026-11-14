<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Ssr\SsrState;
use Symfony\Component\HttpFoundation\Response;

class LogSsrRenderOutcome
{
    /**
     * Log whether the renderer or the client-only fallback produced the page.
     *
     * The renderer failure listener already logs why a render failed; this line
     * lets an operator pair each initial page request with the mode that served
     * it without inspecting the HTML.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $state = app(SsrState::class);

        // The state is scoped, but the test client reuses one application
        // instance across requests. Snapshot the page so a partial reload or a
        // non-Inertia response cannot inherit the previous request's outcome.
        $pageBeforeRequest = $state->page;

        $response = $next($request);

        if ($state->page === [] || $state->page === $pageBeforeRequest) {
            return $response;
        }

        $rendered = $state->response !== null;

        $context = [
            'mode' => $rendered ? 'rendered' : 'fallback',
            'component' => $state->page['component'] ?? null,
            'url' => $state->page['url'] ?? $request->getRequestUri(),
        ];

        if (! $rendered) {
            $context['reason'] = config('inertia.ssr.enabled', true)
                ? 'renderer-unavailable'
                : 'disabled';
        }

        Log::info('Inertia page render outcome.', $context);

        return $response;
    }
}
