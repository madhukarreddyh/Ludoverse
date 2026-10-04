<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use Illuminate\Support\Facades\DB;

/**
 * IPL-format tournament engine.
 *
 * Flow: admin creates (upcoming) -> users register (entry fee debited)
 * -> admin starts the league -> round-robin fixtures -> each fixture's
 * participants join their real Ludo match (bet 0 — entry already paid)
 * before join_deadline_at or take a no-show LOSS -> league points
 * (win = 2, draw = 1-1) -> top 4 -> Qualifier 1 (1st vs 2nd, winner to
 * the final), Eliminator (3rd vs 4th, loser out), Qualifier 2 (Q1 loser
 * vs Eliminator winner, winner to the final) -> Final -> prizes.
 *
 * Prize split (also documented in the tournaments migration): the prize
 * pool is total entry fees minus the `tournament_commission_rate`
 * setting (default 10%). Of the pool: champion 60%, runner-up 25%,
 * third place 15%. In 4v4 each tier is split equally among the winning
 * side's users. Rounding remainders go to the platform, so the ledger
 * always balances: entry debits == prizes + platform share.
 *
 * 4v4: fixtures are played as 4v4 Ludo matches between two sides of up
 * to 4 users. A side with fewer than 3 joined users at match-creation
 * time FORFEITS (the other side takes the win). Short-but-legal sides
 * are padded with bot users so the 8-seat table can start.
 */
class TournamentService
{
    public function __construct(
        protected WalletService $wallets,
        protected MatchService $matches,
    ) {
    }

    // ------------------------------------------------------------------
    // Creation + registration
    // ------------------------------------------------------------------

    public function createTournament(array $attrs, ?User $creator = null): Tournament
    {
        return Tournament::create([
            'name' => $attrs['name'],
            'mode' => $attrs['mode'],
            'entry_fee_paise' => $attrs['entry_fee_paise'] ?? 0,
            'max_participants' => $attrs['max_participants'],
            'starts_at' => $attrs['starts_at'] ?? null,
            'created_by' => $creator?->id,
            'status' => 'upcoming',
        ]);
    }

    /**
     * Register a user: debits the entry fee (type `bet`, reference
     * tournament_{id}_entry_{userId}).
     *
     * @throws LudoException TOURNAMENT_NOT_OPEN / ALREADY_REGISTERED /
     *                       TOURNAMENT_FULL / INSUFFICIENT_BALANCE
     */
    public function register(Tournament $tournament, User $user): TournamentParticipant
    {
        return DB::transaction(function () use ($tournament, $user) {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);

            if ($tournament->status !== 'upcoming') {
                throw new LudoException('TOURNAMENT_NOT_OPEN', 'Registration is closed for this tournament.');
            }
            if ($tournament->participants()->where('user_id', $user->id)->exists()) {
                throw new LudoException('ALREADY_REGISTERED', 'You are already registered.');
            }
            if ($tournament->participants()->count() >= $tournament->max_participants) {
                throw new LudoException('TOURNAMENT_FULL', 'This tournament is full.');
            }

            if ($tournament->entry_fee_paise > 0) {
                if ($this->wallets->balance($user) < $tournament->entry_fee_paise) {
                    throw new LudoException('INSUFFICIENT_BALANCE', 'Top up your wallet to enter.');
                }
                $this->wallets->debit(
                    $user, 'bet', $tournament->entry_fee_paise,
                    "tournament_{$tournament->id}_entry_{$user->id}",
                    ['tournament_id' => $tournament->id],
                );
            }

            return $tournament->participants()->create(['user_id' => $user->id]);
        });
    }

    // ------------------------------------------------------------------
    // League
    // ------------------------------------------------------------------

    /**
     * Move upcoming -> league and generate fixtures: round-robin pairs
     * for 1v1, groups of 8 (two sides of 4) for 4v4.
     *
     * @throws LudoException NOT_ENOUGH_PLAYERS / INVALID_STAGE
     */
    public function startLeague(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);

            if ($tournament->status !== 'upcoming') {
                throw new LudoException('INVALID_STAGE', 'Only upcoming tournaments can start the league.');
            }

            $participants = $tournament->participants()->orderBy('id')->lockForUpdate()->get();
            $count = $participants->count();

            if ($tournament->mode === '1v1' && $count < 4) {
                throw new LudoException('NOT_ENOUGH_PLAYERS', 'A 1v1 tournament needs at least 4 players.');
            }
            if ($tournament->mode === '4v4' && ($count < 16 || $count % 8 !== 0)) {
                throw new LudoException(
                    'NOT_ENOUGH_PLAYERS',
                    'A 4v4 tournament needs a multiple of 8 players (min 16).'
                );
            }

            if ($tournament->mode === '1v1') {
                $ids = $participants->pluck('id')->all();
                for ($i = 0; $i < count($ids); $i++) {
                    for ($j = $i + 1; $j < count($ids); $j++) {
                        $this->makeFixture(
                            $tournament, 'league',
                            $participants->firstWhere('id', $ids[$i]),
                            $participants->firstWhere('id', $ids[$j]),
                        );
                    }
                }
            } else {
                foreach ($participants->chunk(8) as $chunk) {
                    $userIds = $chunk->pluck('user_id')->all();
                    $this->makeSideFixture(
                        $tournament, 'league',
                        array_slice($userIds, 0, 4),
                        array_slice($userIds, 4, 4),
                    );
                }
            }

            $tournament->update(['status' => 'league']);
        });
    }

    // ------------------------------------------------------------------
    // Fixture joining
    // ------------------------------------------------------------------

    /**
     * A participant joins their fixture's match before join_deadline_at.
     * 1v1: both sides joined -> the real (bet 0) Ludo match is created.
     * 4v4: $side (1|2) required; both sides at 4 -> match created,
     * otherwise the join-deadline processor creates it (or forfeits).
     *
     * @throws LudoException FIXTURE_NOT_PENDING / JOIN_DEADLINE_PASSED /
     *                       NOT_A_PARTICIPANT / NOT_YOUR_FIXTURE / SIDE_REQUIRED / SIDE_FULL
     */
    public function joinFixture(TournamentFixture $fixture, User $user, ?int $side = null): TournamentFixture
    {
        return DB::transaction(function () use ($fixture, $user, $side) {
            $fixture = TournamentFixture::lockForUpdate()->findOrFail($fixture->id);
            $tournament = $fixture->tournament;

            if ($fixture->status !== 'pending') {
                throw new LudoException('FIXTURE_NOT_PENDING', 'This fixture is no longer open for joining.');
            }
            if ($fixture->join_deadline_at && $fixture->join_deadline_at->isPast()) {
                throw new LudoException('JOIN_DEADLINE_PASSED', 'The join deadline has passed — recorded as a no-show.');
            }

            $participant = TournamentParticipant::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->first();
            if (! $participant || $participant->status !== 'active') {
                throw new LudoException('NOT_A_PARTICIPANT', 'You are not an active participant.', 403);
            }

            if ($tournament->mode === '1v1') {
                if ((int) $fixture->participant1_id === (int) $participant->id) {
                    $fixture->update(['participant1_joined_at' => now()]);
                } elseif ((int) $fixture->participant2_id === (int) $participant->id) {
                    $fixture->update(['participant2_joined_at' => now()]);
                } else {
                    throw new LudoException('NOT_YOUR_FIXTURE', 'This fixture is not yours.', 403);
                }

                $fixture->refresh();
                if ($fixture->participant1_joined_at && $fixture->participant2_joined_at) {
                    $this->createFixtureMatch($fixture->fresh());
                }
            } else {
                if (! in_array($side, [1, 2], true)) {
                    throw new LudoException('SIDE_REQUIRED', 'Choose side 1 or 2 to join.');
                }
                $side1 = $fixture->side1_user_ids ?? [];
                $side2 = $fixture->side2_user_ids ?? [];
                if (in_array($user->id, $side1, true) || in_array($user->id, $side2, true)) {
                    return $fixture->fresh(); // idempotent re-join
                }

                $column = $side === 1 ? 'side1_user_ids' : 'side2_user_ids';
                $target = $side === 1 ? $side1 : $side2;
                if (count($target) >= 4) {
                    throw new LudoException('SIDE_FULL', 'That side is full.');
                }
                $target[] = $user->id;
                $fixture->update([$column => array_values($target)]);

                $fixture->refresh();
                if (count($fixture->side1_user_ids ?? []) === 4
                    && count($fixture->side2_user_ids ?? []) === 4) {
                    $this->createFixtureMatch($fixture->fresh());
                }
            }

            return $fixture->fresh();
        });
    }

    /**
     * Create the real Ludo contest for a fixture (bet 0 — entry already
     * paid) and mark the fixture ongoing. For 4v4, sides shorter than 4
     * are padded with bot users so the 8-seat table can start; callers
     * must enforce the minimum-3 rule BEFORE calling (see
     * processJoinDeadlines).
     */
    public function createFixtureMatch(TournamentFixture $fixture): \App\Models\LudoMatch
    {
        return DB::transaction(function () use ($fixture) {
            $fixture = TournamentFixture::lockForUpdate()->findOrFail($fixture->id);
            if ($fixture->status !== 'pending') {
                throw new LudoException('FIXTURE_NOT_PENDING', 'Fixture match already created.');
            }
            $tournament = $fixture->tournament;

            if ($tournament->mode === '1v1') {
                $match = $this->matches->createMatchWithSeats([
                    ['user_id' => $fixture->participant1->user_id, 'team' => 0],
                    ['user_id' => $fixture->participant2->user_id, 'team' => 1],
                ], '1v1', 0, ['tournament_fixture_id' => $fixture->id]);
            } else {
                $seats = [];
                foreach ([0 => $fixture->side1_user_ids ?? [], 1 => $fixture->side2_user_ids ?? []] as $team => $userIds) {
                    foreach (array_slice($userIds, 0, 4) as $uid) {
                        $seats[] = ['user_id' => $uid, 'team' => $team];
                    }
                }
                // Pad short sides with bots to fill the 8-seat table.
                $perTeam = [0 => 0, 1 => 0];
                foreach ($seats as $seat) {
                    $perTeam[$seat['team']]++;
                }
                $bots = User::where('role', 'bot')->where('status', 'active')
                    ->orderBy('id')->limit(8)->get();
                $bi = 0;
                foreach ([0, 1] as $team) {
                    while ($perTeam[$team] < 4 && $bi < $bots->count()) {
                        $seats[] = [
                            'user_id' => $bots[$bi]->id, 'team' => $team,
                            'is_bot' => true, 'bot_difficulty' => 'medium',
                        ];
                        $perTeam[$team]++;
                        $bi++;
                    }
                }
                $match = $this->matches->createMatchWithSeats(
                    $seats, '4v4', 0, ['tournament_fixture_id' => $fixture->id]
                );
            }

            $fixture->update(['match_id' => $match->id, 'status' => 'ongoing']);

            return $match;
        });
    }

    /**
     * Driven by matches:tick. Fixtures past their join deadline:
     * - 1v1: a side that never joined takes a no-show LOSS (the other
     *   side wins); neither joined -> both take a loss.
     * - 4v4: a side with fewer than 3 joined users FORFEITS; both short
     *   -> every joined user takes a loss. Otherwise the match is
     *   created (short sides padded with bots).
     */
    public function processJoinDeadlines(): void
    {
        $dueIds = TournamentFixture::where('status', 'pending')
            ->whereNotNull('join_deadline_at')
            ->where('join_deadline_at', '<=', now())
            ->pluck('id');

        foreach ($dueIds as $id) {
            DB::transaction(function () use ($id) {
                $fixture = TournamentFixture::lockForUpdate()->find($id);
                if (! $fixture || $fixture->status !== 'pending') {
                    return;
                }
                $tournament = $fixture->tournament;

                if ($tournament->mode === '1v1') {
                    $p1joined = $fixture->participant1_joined_at !== null;
                    $p2joined = $fixture->participant2_joined_at !== null;

                    if ($p1joined && $p2joined) {
                        // Both joined but the match was never created
                        // (e.g. a crashed request between join and create).
                        $this->createFixtureMatch($fixture);

                        return;
                    }

                    $p1 = $fixture->participant1()->lockForUpdate()->first();
                    $p2 = $fixture->participant2()->lockForUpdate()->first();
                    if ($p1joined && ! $p2joined) {
                        $this->awardFixtureWin($fixture, $p1, $p2); // p2 no-show
                    } elseif ($p2joined && ! $p1joined) {
                        $this->awardFixtureWin($fixture, $p2, $p1); // p1 no-show
                    } else {
                        $p1->increment('losses');
                        $p2->increment('losses');
                        $fixture->update(['status' => 'completed']);
                    }
                } else {
                    $side1 = $fixture->side1_user_ids ?? [];
                    $side2 = $fixture->side2_user_ids ?? [];
                    $c1 = count($side1);
                    $c2 = count($side2);

                    if ($c1 < 3 && $c2 < 3) {
                        foreach (array_merge($side1, $side2) as $uid) {
                            TournamentParticipant::where('tournament_id', $tournament->id)
                                ->where('user_id', $uid)->increment('losses');
                        }
                        $fixture->update(['status' => 'completed']);
                    } elseif ($c1 < 3) {
                        $this->awardSideWin($fixture, 2, 1);
                    } elseif ($c2 < 3) {
                        $this->awardSideWin($fixture, 1, 2);
                    } else {
                        $this->createFixtureMatch($fixture);
                    }
                }
            });
        }
    }

    // ------------------------------------------------------------------
    // Results
    // ------------------------------------------------------------------

    /**
     * Called from MatchService::finishMatch when a fixture match ends.
     * Idempotent — a second call is a no-op. 1v1: win = 2 pts, draw =
     * 1 pt each. 4v4: every user on the winning side gets 2 pts.
     */
    public function recordFixtureResult(TournamentFixture $fixture, \App\Models\LudoMatch $match): void
    {
        DB::transaction(function () use ($fixture, $match) {
            $fixture = TournamentFixture::lockForUpdate()->find($fixture->id);
            if (! $fixture || $fixture->status === 'completed') {
                return;
            }
            $tournament = $fixture->tournament;

            if ($tournament->mode === '1v1') {
                $p1 = $fixture->participant1()->lockForUpdate()->first();
                $p2 = $fixture->participant2()->lockForUpdate()->first();

                if ($match->winner_user_id === null) {
                    $p1->increment('points'); // draw: 1-1
                    $p2->increment('points');
                    $fixture->update(['status' => 'completed']);
                } elseif ((int) $match->winner_user_id === (int) $p1->user_id) {
                    $this->awardFixtureWin($fixture, $p1, $p2);
                } else {
                    $this->awardFixtureWin($fixture, $p2, $p1);
                }
            } else {
                // winning_team 0 => side 1, 1 => side 2.
                $winnerSide = (int) $match->winning_team === 1 ? 2 : 1;
                $this->awardSideWin($fixture, $winnerSide, $winnerSide === 1 ? 2 : 1);
            }
        });
    }

    /**
     * 1v1 win: 2 points + win to the winner, a loss to the loser.
     */
    protected function awardFixtureWin(
        TournamentFixture $fixture,
        TournamentParticipant $winner,
        TournamentParticipant $loser,
    ): void {
        $winner->increment('points', 2);
        $winner->increment('wins');
        $loser->increment('losses');
        $fixture->update([
            'status' => 'completed',
            'winner_participant_id' => $winner->id,
        ]);
    }

    /**
     * 4v4 side win (played or by forfeit): 2 points + win to every
     * joined user on the winning side, a loss to the losing side.
     */
    protected function awardSideWin(TournamentFixture $fixture, int $winnerSide, int $loserSide): void
    {
        $tournamentId = $fixture->tournament_id;

        foreach ($fixture->sideUserIds($winnerSide) as $uid) {
            $p = TournamentParticipant::where('tournament_id', $tournamentId)
                ->where('user_id', $uid)->first();
            if ($p) {
                $p->increment('points', 2);
                $p->increment('wins');
            }
        }
        foreach ($fixture->sideUserIds($loserSide) as $uid) {
            TournamentParticipant::where('tournament_id', $tournamentId)
                ->where('user_id', $uid)->increment('losses');
        }

        $fixture->update(['status' => 'completed', 'winner_side' => $winnerSide]);
    }

    // ------------------------------------------------------------------
    // Knockouts + prizes
    // ------------------------------------------------------------------

    /**
     * Admin stage button: league -> qualifiers, qualifier -> Q2 / final.
     *
     * @throws LudoException INVALID_STAGE / LEAGUE_INCOMPLETE /
     *                       QUALIFIERS_INCOMPLETE / QUALIFIER2_INCOMPLETE
     */
    public function advanceStage(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);

            match ($tournament->status) {
                'league' => $this->generateKnockouts($tournament),
                'qualifier' => $this->generateNextQualifier($tournament),
                default => throw new LudoException(
                    'INVALID_STAGE',
                    "Cannot advance a tournament in '{$tournament->status}' status."
                ),
            };
        });
    }

    /**
     * League done -> top 4: Qualifier 1 (1st vs 2nd) + Eliminator
     * (3rd vs 4th). 4v4 uses the top 16 snake-drafted into two fixtures.
     */
    protected function generateKnockouts(Tournament $tournament): void
    {
        if ($tournament->fixtures()->where('stage', 'league')
            ->where('status', '!=', 'completed')->exists()) {
            throw new LudoException('LEAGUE_INCOMPLETE', 'Finish every league fixture first.');
        }

        $ranked = $tournament->standings();

        if ($tournament->mode === '1v1') {
            if ($ranked->count() < 4) {
                throw new LudoException('NOT_ENOUGH_PLAYERS', 'Need at least 4 ranked players.');
            }
            $top4 = $ranked->take(4)->values();
            foreach ($ranked->skip(4) as $p) {
                $p->update(['status' => 'eliminated']);
            }

            $this->makeFixture($tournament, 'qualifier1', $top4[0], $top4[1]);
            $this->makeFixture($tournament, 'eliminator', $top4[2], $top4[3]);
        } else {
            if ($ranked->count() < 16) {
                throw new LudoException('NOT_ENOUGH_PLAYERS', '4v4 knockouts need 16 ranked players.');
            }
            $top16 = $ranked->take(16)->values();
            foreach ($ranked->skip(16) as $p) {
                $p->update(['status' => 'eliminated']);
            }

            [$q1a, $q1b] = $this->snakeDraft($top16->take(8)->pluck('user_id')->all());
            $this->makeSideFixture($tournament, 'qualifier1', $q1a, $q1b);
            [$ela, $elb] = $this->snakeDraft($top16->slice(8)->take(8)->pluck('user_id')->all());
            $this->makeSideFixture($tournament, 'eliminator', $ela, $elb);
        }

        $tournament->update(['status' => 'qualifier']);
    }

    /**
     * Qualifier done in two steps: first Q1 + Eliminator complete ->
     * Qualifier 2 (Q1 loser vs Eliminator winner); then Q2 complete ->
     * the Final (Q1 winner vs Q2 winner).
     */
    protected function generateNextQualifier(Tournament $tournament): void
    {
        $q1 = $tournament->fixtures()->where('stage', 'qualifier1')->latest('id')->first();
        $eliminator = $tournament->fixtures()->where('stage', 'eliminator')->latest('id')->first();
        $q2 = $tournament->fixtures()->where('stage', 'qualifier2')->latest('id')->first();

        if (! $q1 || ! $eliminator) {
            throw new LudoException('INVALID_STAGE', 'Generate the qualifiers first.');
        }
        if ($q1->status !== 'completed' || $eliminator->status !== 'completed') {
            throw new LudoException('QUALIFIERS_INCOMPLETE', 'Qualifier 1 and the Eliminator must finish first.');
        }

        if (! $q2) {
            if ($tournament->mode === '1v1') {
                $q1loser = $this->fixtureLoser($q1);
                $elWinner = $eliminator->winnerParticipant;
                $elLoser = $this->fixtureLoser($eliminator);
                $elLoser->update(['status' => 'eliminated']);
                $this->makeFixture($tournament, 'qualifier2', $q1loser, $elWinner);
            } else {
                $q1loserSide = $q1->winner_side === 1 ? 2 : 1;
                $elWinnerSide = $eliminator->winner_side;
                $elLoserSide = $elWinnerSide === 1 ? 2 : 1;
                $this->eliminateSide($tournament, $eliminator->sideUserIds($elLoserSide));
                $this->makeSideFixture(
                    $tournament, 'qualifier2',
                    $q1->sideUserIds($q1loserSide),
                    $eliminator->sideUserIds($elWinnerSide),
                );
            }

            return;
        }

        if ($q2->status !== 'completed') {
            throw new LudoException('QUALIFIER2_INCOMPLETE', 'Qualifier 2 must finish first.');
        }

        // Final: Q1 winner vs Q2 winner.
        if ($tournament->mode === '1v1') {
            $this->makeFixture($tournament, 'final', $q1->winnerParticipant, $q2->winnerParticipant);
        } else {
            $this->makeSideFixture(
                $tournament, 'final',
                $q1->sideUserIds($q1->winner_side),
                $q2->sideUserIds($q2->winner_side),
            );
        }
        $tournament->update(['status' => 'final']);
    }

    /**
     * The participant that did NOT win a completed 1v1 fixture.
     */
    protected function fixtureLoser(TournamentFixture $fixture): TournamentParticipant
    {
        return (int) $fixture->winner_participant_id === (int) $fixture->participant1_id
            ? $fixture->participant2
            : $fixture->participant1;
    }

    protected function eliminateSide(Tournament $tournament, array $userIds): void
    {
        TournamentParticipant::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $userIds)
            ->update(['status' => 'eliminated']);
    }

    /**
     * Snake draft: ranks 1,4,5,8 vs 2,3,6,7 — balanced sides.
     *
     * @return array{int[], int[]} [side1 user ids, side2 user ids]
     */
    protected function snakeDraft(array $userIds): array
    {
        $side1 = [];
        $side2 = [];
        foreach (array_chunk(array_values($userIds), 4) as $chunkIndex => $chunk) {
            foreach ($chunk as $i => $uid) {
                if ($chunkIndex % 2 === 0) {
                    $i < 2 ? $side1[] = $uid : $side2[] = $uid;
                } else {
                    $i < 2 ? $side2[] = $uid : $side1[] = $uid;
                }
            }
        }

        return [$side1, $side2];
    }

    // ------------------------------------------------------------------
    // Completion + prizes
    // ------------------------------------------------------------------

    /**
     * Admin "complete" button: the final must be done; prizes go out,
     * status -> completed.
     *
     * @throws LudoException INVALID_STAGE / FINAL_INCOMPLETE
     */
    public function complete(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);

            if ($tournament->status !== 'final') {
                throw new LudoException('INVALID_STAGE', 'Only a tournament at the final can be completed.');
            }
            $final = $tournament->fixtures()->where('stage', 'final')->latest('id')->first();
            if (! $final || $final->status !== 'completed') {
                throw new LudoException('FINAL_INCOMPLETE', 'The final must finish first.');
            }
            // 1v1 Ludo tie-breaks deterministically, so a final always has
            // a winner; guard anyway rather than paying prizes to nobody.
            if ($tournament->mode === '1v1' && ! $final->winner_participant_id) {
                throw new LudoException('FINAL_INCOMPLETE', 'The final has no recorded winner.');
            }

            $this->distributePrizes($tournament, $final);
            $tournament->update(['status' => 'completed']);
        });
    }

    /**
     * Prize pool = entry fees - tournament_commission_rate %. Split:
     * champion 60%, runner-up 25%, third 15% (of the pool). Each tier is
     * split equally among its users (4v4 sides); every rounding
     * remainder plus the commission goes to the platform, so entry
     * debits == prizes + platform share (ledger balanced).
     */
    protected function distributePrizes(Tournament $tournament, TournamentFixture $final): void
    {
        if ($tournament->mode === '1v1') {
            $champion = [$final->winnerParticipant->user_id];
            $runnerUp = [$this->fixtureLoser($final)->user_id];
            $q2 = $tournament->fixtures()->where('stage', 'qualifier2')->latest('id')->first();
            $third = [$this->fixtureLoser($q2)->user_id];
        } else {
            $champion = $final->sideUserIds($final->winner_side);
            $runnerUp = $final->sideUserIds($final->winner_side === 1 ? 2 : 1);
            $q2 = $tournament->fixtures()->where('stage', 'qualifier2')->latest('id')->first();
            $third = $q2->sideUserIds($q2->winner_side === 1 ? 2 : 1);
        }

        $pool = $tournament->participants()->count() * $tournament->entry_fee_paise;
        $rate = (int) (Setting::get('tournament_commission_rate', '10') ?? '10');
        $commission = (int) round($pool * $rate / 100);
        $prizePool = $pool - $commission;

        $paid = 0;
        foreach ([
            ['champion', $champion, 60],
            ['runner-up', $runnerUp, 25],
            ['third', $third, 15],
        ] as [$tier, $userIds, $pct]) {
            $tierTotal = intdiv($prizePool * $pct, 100);
            $perUser = count($userIds) > 0 ? intdiv($tierTotal, count($userIds)) : 0;
            foreach ($userIds as $uid) {
                if ($perUser <= 0) {
                    continue;
                }
                $ref = "tournament_{$tournament->id}_prize_{$tier}_{$uid}";
                if ($this->wallets->referenceExists($ref)) {
                    continue; // idempotent re-run
                }
                $this->wallets->credit(
                    User::findOrFail($uid), 'win', $perUser, $ref,
                    ['tournament_id' => $tournament->id, 'tier' => $tier],
                );
                $paid += $perUser;
            }
        }

        // Commission + all rounding remainders -> platform.
        $toPlatform = $pool - $paid;
        if ($toPlatform > 0 && ! $this->wallets->referenceExists("tournament_{$tournament->id}_commission")) {
            $this->wallets->credit(
                $this->platformUser(), 'commission', $toPlatform,
                "tournament_{$tournament->id}_commission",
                ['tournament_id' => $tournament->id, 'rate' => $rate],
            );
        }
    }

    protected function platformUser(): User
    {
        return User::firstOrCreate(
            ['username' => 'platform'],
            [
                'name' => 'Platform',
                'email' => 'platform@ludoverse.local',
                'password' => str()->random(32),
                'role' => 'system',
                'status' => 'active',
                'my_referral_code' => 'PLATFORM',
                'email_verified_at' => now(),
            ],
        );
    }

    // ------------------------------------------------------------------
    // Fixture builders
    // ------------------------------------------------------------------

    protected function joinDeadline(): \Illuminate\Support\Carbon
    {
        return now()->addMinutes((int) (Setting::get('tournament_join_deadline_minutes', '60') ?? '60'));
    }

    protected function makeFixture(
        Tournament $tournament,
        string $stage,
        TournamentParticipant $p1,
        TournamentParticipant $p2,
    ): TournamentFixture {
        return $tournament->fixtures()->create([
            'stage' => $stage,
            'participant1_id' => $p1->id,
            'participant2_id' => $p2->id,
            'scheduled_at' => now(),
            'join_deadline_at' => $this->joinDeadline(),
            'status' => 'pending',
        ]);
    }

    protected function makeSideFixture(
        Tournament $tournament,
        string $stage,
        array $side1,
        array $side2,
    ): TournamentFixture {
        return $tournament->fixtures()->create([
            'stage' => $stage,
            'side1_user_ids' => array_values($side1),
            'side2_user_ids' => array_values($side2),
            'scheduled_at' => now(),
            'join_deadline_at' => $this->joinDeadline(),
            'status' => 'pending',
        ]);
    }
}
