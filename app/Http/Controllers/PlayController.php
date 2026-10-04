<?php

namespace App\Http\Controllers;

use App\Events\FriendInvite;
use App\Models\Friendship;
use App\Models\LudoMatch;
use App\Models\User;
use App\Services\FraudScanService;
use App\Services\HeuristicVpnCheck;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use App\Services\TableManager;
use App\Services\WalletService;
use Illuminate\Http\Request;

/**
 * Thin JSON controller for the Ludo tables. Every value that matters
 * (dice, legality, scores, winner, money) is computed server-side by
 * MatchService/LudoEngine — the request only ever carries the player's
 * intent: find, roll, which token, exit.
 */
class PlayController extends Controller
{
    public function __construct(
        protected MatchService $matches,
        protected FraudScanService $fraud,
        protected HeuristicVpnCheck $vpn,
    ) {
    }

    /**
     * Seat context recorded on every seat for the collusion scanner.
     */
    protected function seatContext(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'device_hash' => $request->session()->get('device_hash'),
        ];
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|string|in:1v1,2v2,3v3,4v4',
            // Any positive bet: MatchService rejects closed levels with
            // TABLE_CLOSED (the liquidity ladder decides what's open).
            'bet_paise' => 'required|integer|min:1',
            // Optional: invite a friend (by game_id) to a private table
            // instead of joining public matchmaking.
            'invite_game_id' => 'nullable|string|max:16',
        ]);

        // VPN heuristic at matchmaking: flag-only, never a block.
        $vpn = $this->vpn->check($request);
        if ($vpn->suspect) {
            $this->fraud->flagVpnSuspect($request->user(), $vpn->reasons, $request);
        }

        if (! empty($data['invite_game_id'])) {
            return $this->invite($request, $data['invite_game_id'], (int) $data['bet_paise']);
        }

        try {
            $match = $this->matches->findOrCreateMatch(
                $request->user(), $data['mode'], (int) $data['bet_paise'],
                $this->seatContext($request)
            );
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        return response()->json([
            'match_id' => $match->id,
            'status' => $match->status,
            'state' => $this->matches->stateFor($match, $request->user()),
        ]);
    }

    /**
     * Invite a friend to a private 1v1 table: creates the private
     * waiting match and notifies the friend on their `user.{id}` channel.
     */
    protected function invite(Request $request, string $friendGameId, int $betPaise)
    {
        $friend = User::where('game_id', $friendGameId)->first();
        if (! $friend) {
            return response()->json([
                'error' => ['code' => 'USER_NOT_FOUND', 'message' => 'No player with that game ID.'],
            ], 404);
        }

        $me = $request->user();
        if ((int) $friend->id === (int) $me->id) {
            return response()->json([
                'error' => ['code' => 'CANNOT_INVITE_SELF', 'message' => 'You cannot invite yourself.'],
            ], 422);
        }
        if (! Friendship::areFriends((int) $me->id, (int) $friend->id)) {
            return response()->json([
                'error' => ['code' => 'NOT_FRIENDS', 'message' => 'You can only invite friends.'],
            ], 422);
        }

        try {
            $match = $this->matches->createPrivateMatch($me, $friend, $betPaise, $this->seatContext($request));
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        FriendInvite::dispatch(
            (int) $friend->id, $match->id,
            (int) $me->id, (string) $me->name, (string) $me->game_id,
        );

        return response()->json([
            'match_id' => $match->id,
            'status' => $match->status,
            'private' => true,
            'invited_game_id' => $friend->game_id,
            'state' => $this->matches->stateFor($match, $me),
        ], 201);
    }

    /**
     * The invited friend takes their seat at the private table.
     */
    public function joinInvite(Request $request, LudoMatch $match)
    {
        try {
            $match = $this->matches->joinPrivateMatch($match, $request->user(), $this->seatContext($request));
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        return response()->json([
            'match_id' => $match->id,
            'status' => $match->status,
            'state' => $this->matches->stateFor($match, $request->user()),
        ]);
    }

    /**
     * Spectator view: public match state, no auth required. Spectators
     * get the board/scores/players but no legal moves or seat identity,
     * and roll/move still 403 for non-seated users.
     */
    public function watch(LudoMatch $match)
    {
        return response()->json($this->matches->publicStateFor($match));
    }

    public function show(Request $request, LudoMatch $match)
    {
        $this->authorizeSeat($match, $request->user()->id);

        return response()->json($this->matches->stateFor($match, $request->user()));
    }

    public function roll(Request $request, LudoMatch $match)
    {
        $this->authorizeSeat($match, $request->user()->id);

        try {
            $result = $this->matches->roll($match, $request->user());
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        return response()->json($result);
    }

    public function move(Request $request, LudoMatch $match)
    {
        $this->authorizeSeat($match, $request->user()->id);

        $data = $request->validate([
            'token_index' => 'required|integer|min:0|max:3',
        ]);

        try {
            $result = $this->matches->move($match, $request->user(), (int) $data['token_index']);
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        return response()->json($result);
    }

    public function exit(Request $request, LudoMatch $match)
    {
        $this->authorizeSeat($match, $request->user()->id);

        try {
            $this->matches->exit($match, $request->user());
        } catch (LudoException $e) {
            return $this->ludoError($e);
        }

        return response()->json(['exited' => true]);
    }

    /**
     * Game lobby: modes, open bet tables from the liquidity ladder,
     * online count, resume card for the player's active match.
     */
    public function lobby(Request $request)
    {
        $user = $request->user();
        $online = TableManager::onlineCount();
        $bets = TableManager::allowedBets($online);

        $activeMatch = LudoMatch::whereIn('status', ['waiting', 'running'])
            ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->first();

        $balancePaise = app(WalletService::class)->balance($user);

        return view('play.lobby', [
            'modes' => ['1v1', '2v2', '3v3', '4v4'],
            'bets' => $bets,
            'onlineCount' => $online,
            'activeMatch' => $activeMatch,
            'balancePaise' => $balancePaise,
        ]);
    }

    /**
     * Match board UI. Seated players get full controls; everyone else
     * may spectate public matches (private ones stay invite-only).
     */
    public function board(Request $request, LudoMatch $match)
    {
        $user = $request->user();
        $seat = $match->players()->where('user_id', $user->id)->first();

        $spectator = false;
        if (! $seat) {
            if ($match->is_private && (int) $match->invited_user_id !== (int) $user->id) {
                abort(403, 'This is a private match.');
            }
            $spectator = true;
        }

        $names = [];
        foreach ($match->players()->with('user:id,username')->orderBy('id')->get() as $p) {
            $key = $p->user_id !== null ? (int) $p->user_id : 'bot_' . $p->id;
            $names[$key] = $p->is_bot
                ? 'Bot · ' . ($p->bot_difficulty ?? 'auto')
                : ($p->user->username ?? 'Player');
        }

        return view('play.board', [
            'match' => $match,
            'spectator' => $spectator,
            'myColor' => $seat?->color,
            'playerNames' => $names,
        ]);
    }

    /**
     * Simple player profile backing the bottom-nav Profile tab.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $balancePaise = app(WalletService::class)->balance($user);
        $played = \App\Models\MatchPlayer::where('user_id', $user->id)->count();
        $won = LudoMatch::where('winner_user_id', $user->id)->count();

        return view('play.profile', [
            'user' => $user,
            'balancePaise' => $balancePaise,
            'matchesPlayed' => $played,
            'matchesWon' => $won,
        ]);
    }

    /**
     * Only seated humans may touch a match's endpoints.
     */
    protected function authorizeSeat(LudoMatch $match, int $userId): void
    {
        $seated = $match->players()->where('user_id', $userId)->exists();
        if (! $seated) {
            abort(403, 'You are not seated at this match.');
        }
    }

    protected function ludoError(LudoException $e)
    {
        return response()->json([
            'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
        ], $e->http);
    }
}
