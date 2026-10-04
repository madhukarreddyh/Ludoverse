<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode: when settings('maintenance_mode') is on, every public
 * web page returns a 503 maintenance page. /hmkr/* (admin), /install and
 * the /up health check stay reachable so admins can work and turn the
 * mode back off.
 */
class PreventMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::bool('maintenance_mode')) {
            return $next($request);
        }

        if ($request->is('hmkr*') || $request->is('install*') || $request->is('up')) {
            return $next($request);
        }

        abort(503, 'LudoVerse is under maintenance. Please check back soon.');
    }
}
