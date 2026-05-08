<?php

namespace App\Http\Middleware;

use App\Models\Household;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHouseholdAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $household = $user->resolveHousehold();
        if (! $household) {
            // Should not happen — observer creates one on signup. Redirect to safety.
            abort(403, 'Tidak ada household yang terhubung ke akun Anda.');
        }

        // Bind to container & request so views/services can access without re-querying.
        app()->instance('current_household', $household);
        $request->attributes->set('current_household', $household);

        return $next($request);
    }
}
