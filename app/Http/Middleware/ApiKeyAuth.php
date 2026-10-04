<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiKeyLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API key authentication (`api.key` alias).
 *
 * Usage: ->middleware('api.key:public') or ->middleware('api.key:private').
 * The Bearer token is looked up by prefix + sha256 hash; inactive keys get
 * 403, unknown keys 401. Private keys additionally enforce their IP
 * whitelist (403 on mismatch). Every attempt is written to api_key_logs.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, string $requiredType = 'public'): Response
    {
        $bearer = $request->bearerToken();
        $key = $bearer ? ApiKey::findByBearer($bearer) : null;

        $status = 200;
        if (! $key) {
            // Distinguish "no such key" (401) from "known but disabled"
            // (403) without leaking which prefix exists: check the prefix.
            $disabled = $bearer && ApiKey::where('key_prefix', substr($bearer, 0, 12))->where('is_active', false)->exists();
            $status = $disabled ? 403 : 401;
        } elseif ($key->type !== $requiredType) {
            $status = 403;
        } elseif ($requiredType === 'private' && ! $key->ipAllowed($request->ip())) {
            $status = 403;
        }

        if ($key) {
            ApiKeyLog::create([
                'key_id' => $key->id,
                'endpoint' => $request->method().' '.$request->path(),
                'ip' => $request->ip(),
                'status' => $status,
                'created_at' => now(),
            ]);
        }

        if ($status !== 200) {
            return response()->json([
                'error' => $status === 401 ? 'Invalid API key.' : 'API key not authorized for this endpoint.',
            ], $status);
        }

        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
