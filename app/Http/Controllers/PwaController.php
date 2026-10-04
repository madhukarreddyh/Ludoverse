<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the PWA shell files with explicit content types so installs,
 * offline fallback, and tests behave identically behind any web server.
 * (Small files — served from memory rather than streamed.)
 */
class PwaController extends Controller
{
    public function serviceWorker(): Response
    {
        return response(
            (string) file_get_contents(public_path('sw.js')),
            200,
            ['Content-Type' => 'application/javascript; charset=utf-8']
        );
    }

    public function offline(): Response
    {
        return response(
            (string) file_get_contents(public_path('offline.html')),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function manifest(): Response
    {
        return response(
            (string) file_get_contents(public_path('manifest.json')),
            200,
            ['Content-Type' => 'application/manifest+json']
        );
    }
}
