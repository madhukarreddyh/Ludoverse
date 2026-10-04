<?php

namespace App\Http\Middleware;

use App\Services\CheatDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client-integrity gate for the /play JSON endpoints. The X-Client-Version
 * header must match settings('client_version'); a mismatch is LOGGED as a
 * cheat flag (client_version_mismatch) but the request still goes through —
 * outdated clients are common, cheaters are rare, and the escalation to
 * suspension happens inside CheatDetectionService at 3 flags / 24h.
 *
 * The GET match page (plain browser navigation) is skipped: browsers don't
 * send custom headers on navigation, only the JS client does.
 */
class EnsureClientVersion
{
    public function __construct(protected CheatDetectionService $cheat) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('get') && ! $request->expectsJson() && ! $request->ajax()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user) {
            $this->cheat->checkClientVersion($user, $request->header('X-Client-Version'));
        }

        return $next($request);
    }
}
