<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiDocRegistry;
use Illuminate\View\View;

/**
 * GET /api/docs — auto-generated API documentation rendered from the
 * central ApiDocRegistry (method/path/description/params per endpoint,
 * plus auth instructions and curl examples).
 */
class DocsController extends Controller
{
    public function index(): View
    {
        return view('api.docs', [
            'endpoints' => ApiDocRegistry::all(),
        ]);
    }

    /**
     * GET /api/docs/embedding — partner guide for secure iframe embeds
     * with signed tokens (EmbedTokenService::issue / verify).
     */
    public function embedding(): View
    {
        return view('api.embedding');
    }
}
