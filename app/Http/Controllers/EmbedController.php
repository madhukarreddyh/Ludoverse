<?php

namespace App\Http\Controllers;

use App\Models\LudoMatch;
use App\Services\EmbedTokenService;
use App\Services\Ludo\MatchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Secure iframe embed endpoint for partners.
 *
 * GET /embed/match/{match}?token=... — the token is issued server-side
 * by EmbedTokenService::issue() and verified here. Partners embedding
 * this URL get a READ-ONLY, spectator-safe board view: the exact same
 * payload as the public watch page. Game logic (dice, moves, wallets)
 * NEVER leaves the server — the iframe only renders broadcast state.
 */
class EmbedController extends Controller
{
    public function __construct(
        protected EmbedTokenService $tokens,
        protected MatchService $matches,
    ) {}

    public function board(Request $request, LudoMatch $match): View
    {
        $apiKey = $this->tokens->verify((string) $request->query('token'));
        abort_unless($apiKey, 403, 'Invalid or expired embed token.');

        return view('embed.board', [
            'match' => $match,
            'state' => $this->matches->publicStateFor($match),
            'keyName' => $apiKey->name,
        ]);
    }
}
