<?php

namespace App\Http\Controllers;

use App\Models\LudoMatch;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use Illuminate\Http\Request;

/**
 * Thin JSON controller for the Ludo tables. Every value that matters
 * (dice, legality, scores, winner, money) is computed server-side by
 * MatchService/LudoEngine — the request only ever carries the player's
 * intent: find, roll, which token, exit.
 */
class PlayController extends Controller
{
    public function __construct(protected MatchService $matches)
    {
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|string|in:1v1,2v2,3v3,4v4',
            'bet_paise' => 'required|integer|in:500,1000',
        ]);

        try {
            $match = $this->matches->findOrCreateMatch(
                $request->user(), $data['mode'], (int) $data['bet_paise']
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
