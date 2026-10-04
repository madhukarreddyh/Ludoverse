<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Services\Ludo\LudoException;
use App\Services\TournamentService;
use Illuminate\Http\Request;

/**
 * Public tournament pages: list, standings, registration, and joining
 * your fixture's match before the join deadline.
 */
class TournamentController extends Controller
{
    public function __construct(protected TournamentService $tournaments)
    {
    }

    public function index()
    {
        $tournaments = Tournament::whereIn('status', ['upcoming', 'league', 'qualifier', 'final'])
            ->orderByDesc('id')
            ->paginate(20);

        if (request()->expectsJson()) {
            return response()->json(['tournaments' => $tournaments]);
        }

        return view('tournaments.index', compact('tournaments'));
    }

    public function show(Tournament $tournament)
    {
        $tournament->load(['participants.user', 'fixtures']);

        $myParticipant = request()->user()
            ? $tournament->participants()->where('user_id', request()->user()->id)->first()
            : null;

        $payload = [
            'tournament' => $tournament,
            'standings' => $tournament->standings(),
            'fixtures' => $tournament->fixtures()->orderBy('id')->get(),
            'registered' => $myParticipant !== null,
        ];

        if (request()->expectsJson()) {
            return response()->json($payload);
        }

        return view('tournaments.show', $payload);
    }

    public function join(Request $request, Tournament $tournament)
    {
        try {
            $this->tournaments->register($tournament, $request->user());
        } catch (LudoException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                ], $e->http);
            }

            return back()->withErrors(['tournament' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['registered' => true]);
        }

        return back()->with('status', 'Registered — good luck!');
    }

    /**
     * Join your fixture's match before the join deadline.
     * 4v4 requires `side` (1 or 2).
     */
    public function joinFixture(Request $request, TournamentFixture $fixture)
    {
        $data = $request->validate([
            'side' => ['nullable', 'integer', 'in:1,2'],
        ]);

        try {
            $fixture = $this->tournaments->joinFixture(
                $fixture, $request->user(), $data['side'] ?? null
            );
        } catch (LudoException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                ], $e->http);
            }

            return back()->withErrors(['fixture' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'joined' => true,
                'fixture_status' => $fixture->status,
                'match_id' => $fixture->match_id,
            ]);
        }

        return back()->with('status', 'Joined your fixture match.');
    }
}
