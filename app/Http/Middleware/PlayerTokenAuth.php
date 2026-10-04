<?php

namespace App\Http\Middleware;

use App\Models\PlayerToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Player-token authentication for the public game API. The Bearer token
 * is issued by POST /api/v1/public/player/login and expires after 24h.
 * Suspended/frozen accounts are rejected even with a valid token.
 */
class PlayerTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $token = $bearer ? PlayerToken::findByBearer($bearer) : null;

        if (! $token) {
            return response()->json(['error' => 'Invalid or expired player token.'], 401);
        }

        $user = $token->user;
        if (! $user || $user->status !== 'active') {
            return response()->json(['error' => 'This account is not allowed to play right now.'], 403);
        }

        $request->attributes->set('player', $user);

        return $next($request);
    }
}
