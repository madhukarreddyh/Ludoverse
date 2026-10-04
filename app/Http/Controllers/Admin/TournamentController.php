<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Ludo\LudoException;
use App\Services\TournamentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin tournament management: CRUD plus the stage-advance buttons
 * (start league, generate next stage, complete & pay prizes).
 */
class TournamentController extends Controller
{
    public function __construct(protected TournamentService $tournaments)
    {
    }

    public function index(): View
    {
        return view('admin.tournaments.index', [
            'tournaments' => Tournament::orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.tournaments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'in:1v1,4v4'],
            'entry_fee_paise' => ['required', 'integer', 'min:0', 'max:10000000'],
            // 1v1 needs 4+ for a top-4 playoff; 4v4 needs 16+ (multiple of 8).
            'max_participants' => ['required', 'integer', 'min:4', 'max:256'],
            'starts_at' => ['nullable', 'date'],
        ]);

        if ($validated['mode'] === '4v4' && ((int) $validated['max_participants'] % 8 !== 0
            || (int) $validated['max_participants'] < 16)) {
            return back()->withErrors([
                'max_participants' => '4v4 tournaments need a multiple of 8 participants (min 16).',
            ])->withInput();
        }

        $tournament = $this->tournaments->createTournament($validated, $request->user());

        return redirect()->route('hmkr.tournaments.show', $tournament)
            ->with('status', 'Tournament created.');
    }

    public function show(Tournament $tournament): View
    {
        $tournament->load(['participants.user', 'fixtures']);

        return view('admin.tournaments.show', [
            'tournament' => $tournament,
            'standings' => $tournament->standings(),
            'fixtures' => $tournament->fixtures()->orderBy('id')->get(),
        ]);
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        if ($tournament->status !== 'upcoming') {
            return back()->withErrors(['tournament' => 'Only upcoming tournaments can be deleted.']);
        }
        $tournament->delete();

        return redirect()->route('hmkr.tournaments.index')->with('status', 'Tournament deleted.');
    }

    public function startLeague(Tournament $tournament): RedirectResponse
    {
        try {
            $this->tournaments->startLeague($tournament);
        } catch (LudoException $e) {
            return back()->withErrors(['tournament' => $e->getMessage()]);
        }

        return back()->with('status', 'League started — fixtures generated.');
    }

    public function advance(Tournament $tournament): RedirectResponse
    {
        try {
            $this->tournaments->advanceStage($tournament);
        } catch (LudoException $e) {
            return back()->withErrors(['tournament' => $e->getMessage()]);
        }

        return back()->with('status', 'Next stage generated.');
    }

    public function complete(Tournament $tournament): RedirectResponse
    {
        try {
            $this->tournaments->complete($tournament);
        } catch (LudoException $e) {
            return back()->withErrors(['tournament' => $e->getMessage()]);
        }

        return back()->with('status', 'Tournament completed — prizes paid.');
    }
}
