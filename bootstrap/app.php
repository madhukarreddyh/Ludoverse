<?php

use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClientVersion;
use App\Http\Middleware\EnsureLicensed;
use App\Http\Middleware\PlayerTokenAuth;
use App\Http\Middleware\PreventMaintenance;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // License gate on every web route (the middleware itself
        // exempts the /install routes).
        $middleware->appendToGroup('web', EnsureLicensed::class);

        // Maintenance mode: 503 on public pages, /hmkr stays reachable.
        $middleware->appendToGroup('web', PreventMaintenance::class);

        // Online-presence heartbeat for the friends list.
        $middleware->appendToGroup('web', UpdateLastSeen::class);

        // Short aliases.
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'api.key' => ApiKeyAuth::class,
            'player.token' => PlayerTokenAuth::class,
            'client.version' => EnsureClientVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
