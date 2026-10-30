<?php

namespace App\Http\Middleware;

use App\Models\Household;
use Closure;
use Illuminate\Http\Request;

class EnsureHouseholdMember
{
    public function handle(Request $request, Closure $next)
    {
        $household = $request->route('household');

        // 404 rather than 403, so outsiders can't tell whether a household exists.
        abort_unless($household instanceof Household && $request->user()?->roleIn($household), 404);

        return $next($request);
    }
}
