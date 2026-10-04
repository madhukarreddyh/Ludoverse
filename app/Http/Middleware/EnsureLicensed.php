<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * License install gate: every web route redirects to /install until the
 * platform license has been activated. The install routes themselves are
 * always reachable so activation is possible.
 */
class EnsureLicensed
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('install.*')) {
            return $next($request);
        }

        if (! Setting::bool('license_activated')) {
            return redirect()->route('install.show');
        }

        return $next($request);
    }
}
