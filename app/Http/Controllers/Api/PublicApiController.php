<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerToken;
use App\Models\User;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use App\Services\TableManager;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Public player API (/api/v1/public/*).
 *
 * Auth: every call carries EITHER the public API key (player/login) OR a
 * player token (game endpoints) as the Bearer token. The API key
 * identifies the INTEGRATOR; the player token identifies the PLAYER.
 */
class PublicApiController extends Controller
{
    public function __construct(
        protected MatchService $matches,
        protected WalletService $wallets,
    ) {}

    /**
     * Log a player in with game_id or email+password. Returns a player
     * token (24h) for the game endpoints.
     */
    public function playerLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'game_id' => ['nullable', 'string', 'max:16'],
            'email' => ['nullable', 'string', 'email'],
            'password' => ['nullable', 'string'],
        ]);

        $user = null;
        if (! empty($data['game_id'])) {
            $user = User::where('game_id', $data['game_id'])->first();
            // game_id login is trusted the way a session cookie is: the
            // integrator already authenticated the player on their side.
        } elseif (! empty($data['email']) && ! empty($data['password'])) {
            $candidate = User::where('email', $data['email'])->first();
            if ($candidate && Hash::check($data['password'], $candidate->password)) {
                $user = $candidate;
            }
        }

        if (! $user) {
            return response()->json(['error' => 'Invalid player credentials.'], 401);
        }
        if ($user->status !== 'active') {
            return response()->json(['error' => 'This account is '.$user->status.'.'], 403);
        }

        [$token, $plain] = PlayerToken::issue($user);

        return response()->json([
            'player_token' => $plain,
            'expires_at' => $token->expires_at->toIso8601String(),
            'player' => [
                'id' => $user->id,
                'game_id' => $user->game_id,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * Wallet balances in paise: ledger total, locked bonus, spendable.
     */
    public function walletBalance(Request $request): JsonResponse
    {
        $player = $request->attributes->get('player');

        return response()->json([
            'balance_paise' => $this->wallets->balance($player),
            'locked_paise' => $this->wallets->lockedBalance($player),
            'available_paise' => $this->wallets->availableBalance($player),
        ]);
    }

    /**
     * Join matchmaking for a mode + bet.
     */
    public function matchStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'in:1v1,2v2,3v3,4v4'],
            'bet' => ['required', 'integer', 'min:1'],
        ]);

        $player = $request->attributes->get('player');

        try {
            $match = $this->matches->findOrCreateMatch(
                $player, $data['mode'], (int) $data['bet'],
                ['ip_address' => $request->ip(), 'device_hash' => null]
            );
        } catch (LudoException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->http);
        }

        return response()->json([
            'match_id' => $match->id,
            'status' => $match->status,
            'mode' => $match->mode,
            'bet_paise' => $match->bet_paise,
            'open_tables' => TableManager::currentAllowedBets(),
        ], 201);
    }

    /**
     * Join a specific waiting match by id.
     */
    public function matchJoin(Request $request): JsonResponse
    {
        $data = $request->validate(['match_id' => ['required', 'integer']]);

        $match = \App\Models\LudoMatch::find($data['match_id']);
        if (! $match) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        $player = $request->attributes->get('player');

        try {
            $joined = $this->matches->joinSpecificMatch(
                $player, $match,
                ['ip_address' => $request->ip(), 'device_hash' => null]
            );
        } catch (LudoException $e) {
            return response()->json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->http);
        }

        return response()->json(['match_id' => $joined->id, 'status' => $joined->status]);
    }

    /**
     * Finished-match result for a match the player sat at.
     */
    public function matchResult(Request $request, int $id): JsonResponse
    {
        $player = $request->attributes->get('player');

        $match = \App\Models\LudoMatch::with('players')->find($id);
        if (! $match || ! $match->players->contains(fn ($p) => (int) $p->user_id === (int) $player->id)) {
            return response()->json(['error' => 'Match not found.'], 404);
        }

        $payouts = $this->wallets->referenceExists("match_{$match->id}_win_{$player->id}")
            ? \App\Models\WalletLedger::where('reference_id', "match_{$match->id}_win_{$player->id}")->value('amount_paise')
            : 0;

        return response()->json([
            'id' => $match->id,
            'status' => $match->status,
            'mode' => $match->mode,
            'bet_paise' => $match->bet_paise,
            'winner_user_id' => $match->winner_user_id,
            'winning_team' => $match->winning_team,
            'scores' => $match->scores,
            'my_payout_paise' => (int) $payouts,
        ]);
    }
}
