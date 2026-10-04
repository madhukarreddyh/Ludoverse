<?php

namespace App\Services\Ludo;

use App\Events\DiceRolled;
use App\Events\MatchFinished;
use App\Events\MatchStarted;
use App\Events\MoveApplied;
use App\Events\TurnMissed;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\Setting;
use App\Models\User;
use App\Services\InsufficientBalanceException;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Machine-readable error codes returned as 422 JSON from /play/*.
 */
class LudoException extends \RuntimeException
{
    public readonly string $errorCode;

    public readonly int $http;

    public function __construct(string $code, string $message = '', int $http = 422)
    {
        // NOTE: Exception::$code already exists (protected int|string), so
        // the machine-readable code lives in $errorCode instead.
        $this->errorCode = $code;
        $this->http = $http;
        parent::__construct($message ?: $code);
    }
}

/**
 * Server-authoritative match orchestration. The client is untrusted:
 * dice come from LudoEngine::rollDice(), moves are validated by
 * LudoEngine::applyMove(), and scores/winners/payouts are all computed
 * here. The client only ever sends: find, roll request, token choice, exit.
 *
 * Every state-changing entry point runs inside a transaction with a row
 * lock on the match so concurrent roll/move/tick calls serialize.
 */
class MatchService
{
    /**
     * Launch tables: only ₹5 and ₹10 bets for now (paise).
     */
    public const ALLOWED_BETS = [500, 1000];

    public const MODES = ['1v1', '2v2', '3v3', '4v4'];

    /**
     * Seconds a player has to roll / move before the turn is missed.
     */
    public const TURN_SECONDS = 30;

    /**
     * Missed turns before the player is removed from the match.
     */
    public const MAX_MISSED_TURNS = 5;

    /**
     * Seat colours in join order. Classic four first, then interleaved
     * extras so 6- and 8-player tables stay spaced out on the track.
     */
    public const SEAT_COLORS = ['red', 'green', 'yellow', 'blue', 'orange', 'purple', 'teal', 'pink'];

    // Rush scoring (points, not money).
    public const SCORE_HOME = 10;
    public const SCORE_CAPTURE_ATTACKER = 15;
    public const SCORE_CAPTURE_VICTIM = -15;
    public const SCORE_WIN_BONUS = 50;

    public function __construct(
        protected WalletService $wallets,
        protected LudoEngine $engine,
        protected BotStrategy $bots,
        protected ?BotStrategyFactory $botFactory = null,
    ) {
        // $botFactory is optional so older call sites (and tests)
        // constructing MatchService with 3 args keep working.
        $this->botFactory ??= new BotStrategyFactory;
    }

    // ------------------------------------------------------------------
    // Matchmaking
    // ------------------------------------------------------------------

    /**
     * Join a waiting match with the same mode+bet and a free seat,
     * otherwise create a new waiting match. Starts the match when full.
     *
     * @throws LudoException INVALID_MODE / INVALID_BET / ALREADY_IN_MATCH / INSUFFICIENT_BALANCE
     */
    public function findOrCreateMatch(User $user, string $mode, int $betPaise): LudoMatch
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new LudoException('INVALID_MODE', "Mode '{$mode}' is not supported.");
        }
        if (! in_array($betPaise, self::ALLOWED_BETS, true)) {
            throw new LudoException('INVALID_BET', 'Only ₹5 and ₹10 tables are open right now.');
        }

        return DB::transaction(function () use ($user, $mode, $betPaise) {
            // One active match per user — no double-seating.
            $active = MatchPlayer::where('user_id', $user->id)
                ->where('status', 'playing')
                ->whereHas('match', fn ($q) => $q->whereIn('status', ['waiting', 'running']))
                ->first();
            if ($active) {
                throw new LudoException('ALREADY_IN_MATCH', 'Finish or exit your current match first.', 422);
            }

            // Fail fast before taking a seat: the bet is debited at start.
            if ($this->wallets->balance($user) < $betPaise) {
                throw new LudoException('INSUFFICIENT_BALANCE', 'Top up your wallet to join this table.');
            }

            $seats = LudoMatch::seatsForMode($mode);

            // Oldest waiting match with a free seat, locked so two joins
            // cannot take the same last seat. Private (invite-only) tables
            // are invisible to public matchmaking.
            $match = LudoMatch::where('status', 'waiting')
                ->where('mode', $mode)
                ->where('bet_paise', $betPaise)
                ->where('is_private', false)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->first(fn (LudoMatch $m) => $m->players()->count() < $seats);

            if (! $match) {
                $match = LudoMatch::create([
                    'mode' => $mode,
                    'bet_paise' => $betPaise,
                    'status' => 'waiting',
                ]);
            }

            $seatIndex = $match->players()->count();
            $match->players()->create([
                'user_id' => $user->id,
                'team' => $seatIndex < $seats / 2 ? 0 : 1,
                'color' => self::SEAT_COLORS[$seatIndex],
            ]);

            if ($match->players()->count() >= $seats) {
                $this->startMatch($match);
            }

            return $match->fresh();
        });
    }

    /**
     * Create a private 1v1 table for a friend invite. The table is
     * invisible to public matchmaking and bot auto-fill; the invited
     * friend joins via joinPrivateMatch().
     *
     * @throws LudoException INVALID_BET / ALREADY_IN_MATCH / INSUFFICIENT_BALANCE
     */
    public function createPrivateMatch(User $inviter, User $friend, int $betPaise): LudoMatch
    {
        if (! in_array($betPaise, self::ALLOWED_BETS, true)) {
            throw new LudoException('INVALID_BET', 'Only ₹5 and ₹10 tables are open right now.');
        }

        return DB::transaction(function () use ($inviter, $friend, $betPaise) {
            $active = MatchPlayer::where('user_id', $inviter->id)
                ->where('status', 'playing')
                ->whereHas('match', fn ($q) => $q->whereIn('status', ['waiting', 'running']))
                ->first();
            if ($active) {
                throw new LudoException('ALREADY_IN_MATCH', 'Finish or exit your current match first.', 422);
            }

            if ($this->wallets->balance($inviter) < $betPaise) {
                throw new LudoException('INSUFFICIENT_BALANCE', 'Top up your wallet to join this table.');
            }

            $match = LudoMatch::create([
                'mode' => '1v1',
                'bet_paise' => $betPaise,
                'status' => 'waiting',
                'is_private' => true,
                'invited_user_id' => $friend->id,
            ]);

            $match->players()->create([
                'user_id' => $inviter->id,
                'team' => 0,
                'color' => self::SEAT_COLORS[0],
            ]);

            return $match->fresh();
        });
    }

    /**
     * The invited friend takes their seat at a private table. Starts the
     * match once both seats are filled.
     *
     * @throws LudoException NOT_INVITED / ALREADY_IN_MATCH / INSUFFICIENT_BALANCE
     */
    public function joinPrivateMatch(LudoMatch $match, User $user): LudoMatch
    {
        return DB::transaction(function () use ($match, $user) {
            $match = LudoMatch::lockForUpdate()->findOrFail($match->id);

            if (! $match->is_private || $match->status !== 'waiting') {
                throw new LudoException('NOT_INVITED', 'This invite is no longer open.');
            }
            if ((int) $match->invited_user_id !== (int) $user->id) {
                throw new LudoException('NOT_INVITED', 'This private table is not for you.', 403);
            }
            if ($match->players()->where('user_id', $user->id)->exists()) {
                return $match; // idempotent re-join
            }

            $active = MatchPlayer::where('user_id', $user->id)
                ->where('status', 'playing')
                ->whereHas('match', fn ($q) => $q->whereIn('status', ['waiting', 'running']))
                ->first();
            if ($active) {
                throw new LudoException('ALREADY_IN_MATCH', 'Finish or exit your current match first.', 422);
            }

            if ($this->wallets->balance($user) < $match->bet_paise) {
                throw new LudoException('INSUFFICIENT_BALANCE', 'Top up your wallet to join this table.');
            }

            $match->players()->create([
                'user_id' => $user->id,
                'team' => 1,
                'color' => self::SEAT_COLORS[1],
            ]);

            if ($match->players()->count() >= LudoMatch::seatsForMode($match->mode)) {
                $this->startMatch($match->fresh());
            }

            return $match->fresh();
        });
    }

    /**
     * Create a match with an explicit seat list and start it immediately.
     * Used by the tournament engine: bet 0 (entry already paid), real
     * humans on both sides, bots only as padding in 4v4.
     *
     * Each seat: ['user_id' => ?int, 'team' => int, 'is_bot' => bool,
     *             'bot_difficulty' => ?string]. Seats fill colours in order.
     */
    public function createMatchWithSeats(array $seats, string $mode, int $betPaise, array $extra = []): LudoMatch
    {
        return DB::transaction(function () use ($seats, $mode, $betPaise, $extra) {
            $match = LudoMatch::create(array_merge([
                'mode' => $mode,
                'bet_paise' => $betPaise,
                'status' => 'waiting',
            ], $extra));

            foreach (array_values($seats) as $i => $seat) {
                $match->players()->create([
                    'user_id' => $seat['user_id'] ?? null,
                    'is_bot' => $seat['is_bot'] ?? false,
                    'bot_difficulty' => $seat['bot_difficulty'] ?? null,
                    'team' => $seat['team'],
                    'color' => self::SEAT_COLORS[$i],
                ]);
            }

            $this->startMatch($match->fresh());

            return $match->fresh();
        });
    }

    /**
     * A full table goes live: debit every human's bet, set the clock,
     * deal the first turn.
     */
    public function startMatch(LudoMatch $match): void
    {
        if ($match->status !== 'waiting') {
            return;
        }

        $seats = LudoMatch::seatsForMode($match->mode);
        $players = $match->players()->orderBy('id')->lockForUpdate()->get();

        if ($players->count() < $seats) {
            return; // not full yet — stay waiting
        }

        // Balance check under lock BEFORE any debit, so a broke joiner is
        // removed cleanly instead of leaving a half-debited table.
        foreach ($players as $player) {
            if (! $player->isHuman()) {
                continue;
            }
            $user = $player->user()->lockForUpdate()->first();
            if ($this->wallets->balance($user) < $match->bet_paise) {
                $player->delete();
                throw new LudoException('INSUFFICIENT_BALANCE', 'A player could not cover the bet.');
            }
        }

        foreach ($players as $player) {
            if (! $player->isHuman()) {
                continue;
            }
            // Tournament fixtures run bet 0 (entry was already paid) —
            // WalletService::debit rejects non-positive amounts, so skip.
            if ($match->bet_paise <= 0) {
                break;
            }
            // Idempotent reference: a retried start can never double-debit.
            $this->wallets->debit(
                $player->user, 'bet', $match->bet_paise,
                "match_{$match->id}_bet_{$player->user_id}",
                ['match_id' => $match->id, 'mode' => $match->mode],
            );
        }

        $board = [];
        foreach ($players as $player) {
            $board[$player->color] = array_fill(0, LudoEngine::TOKENS_PER_PLAYER, LudoEngine::BASE);
        }

        $match->update([
            'status' => 'running',
            'board_state' => $board,
            'scores' => $this->scoresJson($players),
            'ends_at' => now()->addMinutes(LudoMatch::durationMinutesForMode($match->mode)),
            'pending_dice' => null,
            'consecutive_sixes' => 0,
        ]);

        $first = $players->first();
        $this->dealTurn($match->fresh(), $first);

        MatchStarted::dispatch($match->fresh(), $this->playersPayload($match->fresh()));
    }

    // ------------------------------------------------------------------
    // Turn flow: roll -> move
    // ------------------------------------------------------------------

    /**
     * Server rolls the dice for the player whose turn it is.
     *
     * @throws LudoException MATCH_NOT_RUNNING / NOT_YOUR_TURN / ALREADY_ROLLED
     */
    public function roll(LudoMatch $match, User $user): array
    {
        return DB::transaction(function () use ($match, $user) {
            $match = LudoMatch::lockForUpdate()->findOrFail($match->id);
            $player = $this->requirePlayingHuman($match, $user, forRoll: true);

            $dice = $this->engine->rollDice();
            $board = $match->board_state;

            if ($dice === 6) {
                $match->increment('consecutive_sixes');
                $match->refresh();

                // Three sixes in a row: turn forfeited, no move allowed.
                if ($match->consecutive_sixes >= 3) {
                    $match->update(['consecutive_sixes' => 0, 'pending_dice' => null]);
                    TurnMissed::dispatch($match, $user->id, $player->missed_turns, 'forfeit');
                    $this->advanceFromPlayer($match, $player);

                    return ['dice' => $dice, 'forfeited' => true];
                }
            }

            $legal = $this->engine->legalMoves($board, $player->color, $dice);

            // Nothing can move: the turn auto-passes, no penalty.
            if ($legal === []) {
                $match->update(['pending_dice' => null, 'consecutive_sixes' => 0]);
                DiceRolled::dispatch($match, $user->id, $dice, [], true);
                $this->advanceFromPlayer($match, $player);

                return ['dice' => $dice, 'legal_moves' => [], 'auto_passed' => true];
            }

            $match->update([
                'pending_dice' => $dice,
                'turn_deadline_at' => now()->addSeconds(self::TURN_SECONDS),
            ]);

            DiceRolled::dispatch($match, $user->id, $dice, $legal, false);

            return ['dice' => $dice, 'legal_moves' => $legal];
        });
    }

    /**
     * Apply the player's chosen token move for the pending dice.
     *
     * @throws LudoException MATCH_NOT_RUNNING / NOT_YOUR_TURN / NO_DICE / ILLEGAL_MOVE
     */
    public function move(LudoMatch $match, User $user, int $tokenIndex): array
    {
        return DB::transaction(function () use ($match, $user, $tokenIndex) {
            $match = LudoMatch::lockForUpdate()->findOrFail($match->id);
            $player = $this->requirePlayingHuman($match, $user);

            $dice = $match->pending_dice;
            if ($dice === null) {
                throw new LudoException('NO_DICE', 'Roll the dice first.');
            }

            $board = $match->board_state;
            try {
                $result = $this->engine->applyMove($board, $player->color, $tokenIndex, $dice);
            } catch (IllegalMoveException $e) {
                // Transaction rolls back: illegal moves change nothing.
                throw new LudoException('ILLEGAL_MOVE', $e->getMessage());
            }

            $players = $match->players()->orderBy('id')->get();
            $this->applyScoreEvents($match, $players, $result['events']);

            $match->update([
                'board_state' => $result['board'],
                'scores' => $this->scoresJson($match->players()->orderBy('id')->get()),
                'pending_dice' => null,
            ]);

            // All four tokens home: the player is done, +50 win bonus.
            $finishedNow = $this->engine->isFinished($result['board'], $player->color);
            if ($finishedNow && $player->status === 'playing') {
                $player->update(['status' => 'finished']);
                $this->bumpScore($match, $player, self::SCORE_WIN_BONUS);
                $match->update(['scores' => $this->scoresJson($match->players()->orderBy('id')->get())]);
            }

            $next = $this->nextPlayingPlayer($match, $player);

            MoveApplied::dispatch(
                $match->fresh(), $user->id, $tokenIndex, $dice,
                $result['events'], $match->fresh()->scores ?? [], $next?->user_id,
            );

            // A 6 earns another roll; anything else passes the turn.
            // (Sixes counter was already incremented at roll time.)
            if ($dice !== 6) {
                $match->update(['consecutive_sixes' => 0]);
            }

            if ($this->shouldFinish($match->fresh())) {
                $this->finishMatch($match);
            } elseif ($dice === 6 && ! $finishedNow) {
                // Same player rolls again.
                $match->update(['turn_deadline_at' => now()->addSeconds(self::TURN_SECONDS)]);
            } else {
                $this->advanceFromPlayer($match->fresh(), $player->fresh());
            }

            return [
                'events' => $result['events'],
                'board' => $result['board'],
                'scores' => $match->fresh()->scores,
            ];
        });
    }

    // ------------------------------------------------------------------
    // Exit
    // ------------------------------------------------------------------

    /**
     * A player leaves mid-match. The bet is NOT refunded; their tokens
     * leave the board and their score stands for the final tally.
     */
    public function exit(LudoMatch $match, User $user): void
    {
        DB::transaction(function () use ($match, $user) {
            $match = LudoMatch::lockForUpdate()->findOrFail($match->id);

            $player = $match->players()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $player) {
                throw new LudoException('NOT_A_PLAYER', 'You are not seated at this match.');
            }

            // Leaving the lobby: no money moved yet, just free the seat.
            if ($match->status === 'waiting') {
                $player->delete();
                if ($match->players()->count() === 0) {
                    $match->update(['status' => 'cancelled']);
                }

                return;
            }

            $this->ensureRunning($match);

            if ($player->status !== 'playing') {
                return; // already out — idempotent
            }

            $wasTurn = $match->current_turn_user_id === $user->id;

            $player->update(['status' => 'lost']);

            // Their tokens leave the board so nobody can capture ghosts.
            $board = $match->board_state ?? [];
            if (isset($board[$player->color])) {
                $board[$player->color] = array_fill(0, LudoEngine::TOKENS_PER_PLAYER, LudoEngine::BASE);
                $match->update(['board_state' => $board]);
            }

            if ($this->shouldFinish($match->fresh())) {
                $this->finishMatch($match);

                return;
            }

            if ($wasTurn) {
                $this->advanceFromPlayer($match->fresh(), $player);
            }
        });
    }

    // ------------------------------------------------------------------
    // Finish + payouts
    // ------------------------------------------------------------------

    /**
     * Settle the match: pick the winner, take commission, pay winners.
     * Idempotent — a second call (or a retried tick) is a no-op.
     */
    public function finishMatch(LudoMatch $match): void
    {
        DB::transaction(function () use ($match) {
            $match = LudoMatch::lockForUpdate()->findOrFail($match->id);
            if ($match->status !== 'running') {
                return;
            }

            $players = $match->players()->orderBy('id')->lockForUpdate()->get();

            [$winnerUserId, $winningTeam] = $this->decideWinner($match, $players);

            $humans = $players->filter->isHuman();
            $pot = $match->bet_paise * $humans->count();

            $rate = (int) (Setting::get('match_commission_rate', '10') ?? '10');
            $commissionBase = (int) round($pot * $rate / 100);

            // Only players still in the game on the winning team get paid —
            // quitters (lost) forfeit their share to the remaining winners.
            $eligible = $humans->where('team', $winningTeam)
                ->whereIn('status', ['playing', 'finished']);
            if ($eligible->isEmpty()) {
                $eligible = $humans->where('team', $winningTeam);
            }

            $payouts = [];
            $share = $eligible->isNotEmpty() ? intdiv($pot - $commissionBase, $eligible->count()) : 0;
            // Remainder from the integer split goes to the platform, so
            // pot == commission + payouts always (ledger stays balanced).
            $commission = $pot - $share * $eligible->count();

            $platform = $this->platformUser();
            if ($commission > 0 && ! $this->wallets->referenceExists("match_{$match->id}_commission")) {
                $this->wallets->credit(
                    $platform, 'commission', $commission, "match_{$match->id}_commission",
                    ['match_id' => $match->id, 'rate' => $rate],
                );
            }

            foreach ($eligible as $winner) {
                if ($share > 0 && ! $this->wallets->referenceExists("match_{$match->id}_win_{$winner->user_id}")) {
                    $this->wallets->credit(
                        $winner->user, 'win', $share, "match_{$match->id}_win_{$winner->user_id}",
                        ['match_id' => $match->id, 'team' => $winningTeam],
                    );
                    $payouts[] = ['user_id' => $winner->user_id, 'amount_paise' => $share];
                }
            }

            $match->update([
                'status' => 'finished',
                'winner_user_id' => $winnerUserId,
                'winning_team' => $winningTeam,
                'pending_dice' => null,
            ]);

            MatchFinished::dispatch(
                $match->fresh(), $winnerUserId, $winningTeam,
                $match->fresh()->scores ?? [], $payouts,
            );

            // Tournament fixture match: feed the result into the points
            // table. Resolved lazily (not constructor-injected) to avoid
            // a MatchService <-> TournamentService construction cycle.
            if ($match->tournament_fixture_id) {
                $fixture = \App\Models\TournamentFixture::find($match->tournament_fixture_id);
                if ($fixture) {
                    app(\App\Services\TournamentService::class)
                        ->recordFixtureResult($fixture, $match->fresh());
                }
            }
        });
    }

    /**
     * 1v1: highest individual score. Team modes: highest team total.
     * Ties break to the lowest seat id — deterministic, no randomness.
     *
     * @return array{?int, ?int} [winner_user_id, winning_team]
     */
    protected function decideWinner(LudoMatch $match, $players): array
    {
        // Quitters never win: 'lost' players are excluded unless everyone
        // lost (total abandonment — then highest score still decides).
        $contenders = $players->where('status', '!=', 'lost');
        if ($contenders->isEmpty()) {
            $contenders = $players;
        }

        if ($match->mode === '1v1') {
            $best = $contenders->where('is_bot', false)->sortBy([
                ['score', 'desc'],
                ['id', 'asc'],
            ])->first();

            return [$best?->user_id, $best?->team];
        }

        $totals = [];
        foreach ($players as $player) {
            $totals[$player->team] = ($totals[$player->team] ?? 0) + $player->score;
        }
        arsort($totals);
        $winningTeam = (int) array_key_first($totals);

        // Highest scorer on the winning team, humans preferred for the
        // displayed winner (bots hold no wallet).
        $best = $contenders->where('team', $winningTeam)->sortBy([
            ['score', 'desc'],
            ['id', 'asc'],
        ])->first() ?? $players->where('team', $winningTeam)->sortBy([
            ['score', 'desc'],
            ['id', 'asc'],
        ])->first();

        return [$best && ! $best->is_bot ? $best->user_id : null, $winningTeam];
    }

    // ------------------------------------------------------------------
    // Tick (artisan matches:tick)
    // ------------------------------------------------------------------

    /**
     * Bot auto-fill (bot economy): a public waiting table on an allowed
     * bet level that has waited longer than `bot_join_after_seconds`
     * gets a bot seated, up to `bot_max_per_match` bots per match.
     *
     * Bots are role=bot users seated with is_bot=true, so the existing
     * wallet rule holds: they are never debited or credited. The bot
     * plays with the admin's configured `bot_difficulty` strategy.
     *
     * HONESTY NOTE: seating harder bots shifts win RATES statistically
     * in the house's favour over many games, but no difficulty setting
     * targets an exact win ratio — dice variance makes that impossible
     * to guarantee per game or per player.
     */
    protected function fillWaitingMatchesWithBots(): void
    {
        if (! Setting::bool('bot_fill_enabled')) {
            return;
        }

        $tables = json_decode((string) Setting::get('bot_tables', '["500","1000"]'), true);
        if (! is_array($tables) || $tables === []) {
            return;
        }
        $tables = array_map('intval', $tables);

        $maxBots = (int) Setting::get('bot_max_per_match', '1');
        $afterSeconds = (int) Setting::get('bot_join_after_seconds', '20');
        $difficulty = (string) Setting::get('bot_difficulty', 'medium');
        if ($maxBots < 1) {
            return;
        }

        $waiting = LudoMatch::where('status', 'waiting')
            ->where('is_private', false)
            ->whereIn('bet_paise', $tables)
            ->where('created_at', '<=', now()->subSeconds(max(0, $afterSeconds)))
            ->orderBy('id')
            ->pluck('id');

        foreach ($waiting as $id) {
            DB::transaction(function () use ($id, $maxBots, $difficulty) {
                $match = LudoMatch::lockForUpdate()->find($id);
                if (! $match || $match->status !== 'waiting') {
                    return;
                }

                $seats = LudoMatch::seatsForMode($match->mode);
                $players = $match->players()->lockForUpdate()->get();
                if ($players->count() >= $seats) {
                    return;
                }
                if ($players->where('is_bot', true)->count() >= $maxBots) {
                    return;
                }

                // A bot user already seated at any live table is busy.
                $busyIds = MatchPlayer::where('is_bot', true)
                    ->whereIn('status', ['playing', 'finished'])
                    ->whereHas('match', fn ($q) => $q->whereIn('status', ['waiting', 'running']))
                    ->pluck('user_id')
                    ->filter()
                    ->all();

                $bot = User::where('role', 'bot')
                    ->where('status', 'active')
                    ->whereNotIn('id', $busyIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                if (! $bot) {
                    return;
                }

                $seatIndex = $players->count();
                $match->players()->create([
                    'user_id' => $bot->id,
                    'is_bot' => true,
                    'bot_difficulty' => $difficulty,
                    'team' => $seatIndex < $seats / 2 ? 0 : 1,
                    'color' => self::SEAT_COLORS[$seatIndex],
                ]);

                if ($match->players()->count() >= $seats) {
                    $this->startMatch($match->fresh());
                }
            });
        }
    }

    /**
     * Advance all running matches: expire the match timer, punish missed
     * turns (5 misses = removal), and auto-play bot turns.
     *
     * Also drives the two waiting-room jobs: bot auto-fill for tables
     * that have waited too long, and tournament join-deadline processing
     * (no-shows become losses).
     */
    public function tick(): void
    {
        $this->fillWaitingMatchesWithBots();

        app(\App\Services\TournamentService::class)->processJoinDeadlines();

        $ids = LudoMatch::where('status', 'running')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $match = LudoMatch::lockForUpdate()->find($id);
                if (! $match || $match->status !== 'running') {
                    return;
                }

                // Match clock ran out: highest score wins, immediately.
                if ($match->ends_at && $match->ends_at->isPast()) {
                    $this->finishMatch($match);

                    return;
                }

                if (! $match->turn_deadline_at || ! $match->turn_deadline_at->isPast()) {
                    return;
                }

                $player = $match->players()
                    ->where('user_id', $match->current_turn_user_id)
                    ->lockForUpdate()
                    ->first();

                // Bots never miss — they act instantly when dealt a turn.
                if ($player && $player->status === 'playing' && ! $player->is_bot) {
                    $player->increment('missed_turns');
                    $player->refresh();

                    TurnMissed::dispatch($match, $player->user_id, $player->missed_turns, 'timeout');

                    if ($player->missed_turns >= self::MAX_MISSED_TURNS) {
                        $player->update(['status' => 'lost']);
                        $this->clearTokens($match, $player);
                    }
                }

                if ($this->shouldFinish($match->fresh())) {
                    $this->finishMatch($match);

                    return;
                }

                // Advance past lost/finished players; bots play instantly.
                $anchor = $player ?? $match->players()->orderBy('id')->first();
                if ($anchor) {
                    $this->advanceFromPlayer($match->fresh(), $anchor);
                }
            });
        }
    }

    // ------------------------------------------------------------------
    // Turn plumbing
    // ------------------------------------------------------------------

    /**
     * Deal the turn to $player: deadline, clear stale dice, auto-play bots.
     */
    protected function dealTurn(LudoMatch $match, MatchPlayer $player): void
    {
        $match->update([
            'current_turn_user_id' => $player->user_id,
            'turn_deadline_at' => now()->addSeconds(self::TURN_SECONDS),
            'pending_dice' => null,
        ]);

        if ($player->is_bot) {
            $this->playBotTurn($match->fresh(), $player);
            // The bot's turn resolved instantly — pass the turn on, unless
            // the bot's play already ended the match.
            $match = $match->fresh();
            if ($match->status === 'running') {
                $this->advanceFromPlayer($match, $player->fresh());
            }
        }
    }

    /**
     * Move the turn to the next seated player with status 'playing'
     * (seat order, wrapping). Ends the match when nobody can play or only
     * one team is still alive.
     */
    protected function advanceFromPlayer(LudoMatch $match, MatchPlayer $fromPlayer): void
    {
        $match = $match->fresh();
        if ($match->status !== 'running') {
            return;
        }

        if ($this->shouldFinish($match)) {
            $this->finishMatch($match);

            return;
        }

        $next = $this->nextPlayingPlayer($match, $fromPlayer);
        if (! $next) {
            $this->finishMatch($match);

            return;
        }

        $this->dealTurn($match, $next);
    }

    /**
     * Next player in seat order (wrapping) whose status is 'playing'.
     */
    protected function nextPlayingPlayer(LudoMatch $match, MatchPlayer $fromPlayer): ?MatchPlayer
    {
        $ordered = $match->players()->orderBy('id')->get();
        if ($ordered->isEmpty()) {
            return null;
        }

        $ids = $ordered->pluck('id')->all();
        $pos = array_search($fromPlayer->id, $ids, true);
        $pos = $pos === false ? -1 : $pos;

        for ($i = 1; $i <= count($ids); $i++) {
            $candidate = $ordered[($pos + $i) % count($ids)];
            if ($candidate->status === 'playing') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The match is over when nobody is left playing, or at most one team
     * is still in contention. A team with humans is out once ALL of them
     * have quit/timed-out (a bot teammate doesn't save it — the humans'
     * money decided the contest); a bot-only team is out when no bot is
     * still playing.
     */
    protected function shouldFinish(LudoMatch $match): bool
    {
        $players = $match->players()->get();

        if ($players->where('status', 'playing')->isEmpty()) {
            return true;
        }

        $aliveTeams = [];
        foreach ($players->groupBy('team') as $team => $members) {
            $humans = $members->filter->isHuman();
            if ($humans->isNotEmpty()) {
                if ($humans->where('status', 'lost')->count() < $humans->count()) {
                    $aliveTeams[] = $team;
                }
            } elseif ($members->where('status', 'playing')->isNotEmpty()) {
                $aliveTeams[] = $team;
            }
        }

        return count(array_unique($aliveTeams)) <= 1;
    }

    /**
     * A bot's whole turn, played instantly on the server: roll, move,
     * re-roll on sixes. Loops (never recurses) with a safety cap.
     */
    protected function playBotTurn(LudoMatch $match, MatchPlayer $bot): void
    {
        // Bot turns resolve synchronously, so consecutive sixes live in a
        // local counter — no cross-request DB state to go stale.
        $sixes = (int) $match->consecutive_sixes;

        for ($i = 0; $i < 50; $i++) {
            $match = $match->fresh();
            if ($match->status !== 'running' || $bot->fresh()->status !== 'playing') {
                return;
            }

            $dice = $this->engine->rollDice();

            if ($dice === 6) {
                $sixes++;
                if ($sixes >= 3) {
                    $match->update(['consecutive_sixes' => 0]);
                    TurnMissed::dispatch($match->fresh(), null, 0, 'forfeit');
                    return;
                }
            }

            $board = $match->board_state;
            $legal = $this->engine->legalMoves($board, $bot->color, $dice);

            if ($legal === []) {
                $match->update(['consecutive_sixes' => 0]);
                DiceRolled::dispatch($match, null, $dice, [], true);
                return;
            }

            $token = $this->botFactory->forDifficulty($bot->bot_difficulty)
                ->chooseMove($legal, $board, $bot->color, $dice);
            // $token came from legalMoves, so this cannot throw.
            $result = $this->engine->applyMove($board, $bot->color, $token, $dice);

            $players = $match->players()->orderBy('id')->get();
            $this->applyScoreEvents($match, $players, $result['events']);

            if ($dice !== 6) {
                $sixes = 0;
            }
            $match->update([
                'board_state' => $result['board'],
                'scores' => $this->scoresJson($match->players()->orderBy('id')->get()),
                'consecutive_sixes' => $sixes,
            ]);

            $bot->refresh();
            if ($this->engine->isFinished($result['board'], $bot->color) && $bot->status === 'playing') {
                $bot->update(['status' => 'finished']);
                $this->bumpScore($match->fresh(), $bot->fresh(), self::SCORE_WIN_BONUS);
                $match->update(['scores' => $this->scoresJson($match->players()->orderBy('id')->get())]);
            }

            $next = $this->nextPlayingPlayer($match->fresh(), $bot->fresh());
            MoveApplied::dispatch(
                $match->fresh(), null, $token, $dice,
                $result['events'], $match->fresh()->scores ?? [], $next?->user_id,
            );

            if ($this->shouldFinish($match->fresh())) {
                $this->finishMatch($match);
                return;
            }

            if ($dice !== 6 || $bot->fresh()->status !== 'playing') {
                return; // turn passes back to advanceFromPlayer's caller
            }
            // Rolled a 6: the bot rolls again.
        }
    }

    // ------------------------------------------------------------------
    // Scoring
    // ------------------------------------------------------------------

    /**
     * Convert engine events into rush points: home +10, capture +15 to
     * the attacker and −15 to the victim. Victim lookup is by colour, so
     * it works for humans and bots alike.
     */
    protected function applyScoreEvents(LudoMatch $match, $players, array $events): void
    {
        $byColor = $players->keyBy('color');

        foreach ($events as $event) {
            match ($event['type']) {
                'home' => $this->bumpScore(
                    $match, $byColor[$event['color']] ?? null, self::SCORE_HOME
                ),
                'capture' => (function () use ($match, $byColor, $event) {
                    $this->bumpScore(
                        $match, $byColor[$event['attacker_color']] ?? null,
                        self::SCORE_CAPTURE_ATTACKER
                    );
                    $this->bumpScore(
                        $match, $byColor[$event['victim_color']] ?? null,
                        self::SCORE_CAPTURE_VICTIM
                    );
                })(),
                default => null,
            };
        }
    }

    protected function bumpScore(LudoMatch $match, ?MatchPlayer $player, int $delta): void
    {
        if (! $player || $delta === 0) {
            return;
        }
        $player->increment('score', $delta);
    }

    /**
     * Rebuild the scores JSON from the players table (single source of
     * truth for points). Humans keyed by user_id, bots by "bot_{playerId}".
     */
    protected function scoresJson($players): array
    {
        $scores = [];
        foreach ($players as $player) {
            $key = $player->isHuman() ? (string) $player->user_id : "bot_{$player->id}";
            $scores[$key] = $player->score;
        }

        return $scores;
    }

    protected function clearTokens(LudoMatch $match, MatchPlayer $player): void
    {
        $board = $match->board_state ?? [];
        if (isset($board[$player->color])) {
            $board[$player->color] = array_fill(0, LudoEngine::TOKENS_PER_PLAYER, LudoEngine::BASE);
            $match->update(['board_state' => $board]);
        }
    }

    // ------------------------------------------------------------------
    // Guards + helpers
    // ------------------------------------------------------------------

    /**
     * The player must be a seated, still-playing human whose turn it is.
     * Bots act only through the server's auto-play, never via HTTP.
     *
     * @throws LudoException MATCH_NOT_RUNNING / NOT_A_PLAYER / NOT_YOUR_TURN
     */
    protected function requirePlayingHuman(LudoMatch $match, User $user, bool $forRoll = false): MatchPlayer
    {
        $this->ensureRunning($match);

        $player = $match->players()->where('user_id', $user->id)->first();
        if (! $player || $player->status !== 'playing' || ! $player->isHuman()) {
            throw new LudoException('NOT_YOUR_TURN', 'You are not an active player at this table.');
        }
        if ((int) $match->current_turn_user_id !== (int) $user->id) {
            throw new LudoException('NOT_YOUR_TURN', 'Wait for your turn.');
        }
        // No dice-fishing: one roll per turn until the token moves.
        if ($forRoll && $match->pending_dice !== null) {
            throw new LudoException('ALREADY_ROLLED', 'Dice already rolled — move a token first.');
        }

        return $player;
    }

    /**
     * @throws LudoException MATCH_NOT_RUNNING
     */
    protected function ensureRunning(LudoMatch $match): void
    {
        if ($match->status !== 'running') {
            throw new LudoException('MATCH_NOT_RUNNING', 'This match is not running.');
        }
        // The clock is authoritative: a match past its end settles on
        // next contact instead of accepting more moves.
        if ($match->ends_at && $match->ends_at->isPast()) {
            $this->finishMatch($match);
            throw new LudoException('MATCH_NOT_RUNNING', 'Time expired — the match has ended.');
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

    protected function playersPayload(LudoMatch $match): array
    {
        return $match->players()->orderBy('id')->get()->map(fn (MatchPlayer $p) => [
            'user_id' => $p->user_id,
            'team' => $p->team,
            'color' => $p->color,
            'is_bot' => $p->is_bot,
            'score' => $p->score,
            'status' => $p->status,
        ])->all();
    }

    /**
     * Public read model for GET /play/match/{id}. Includes the pending
     * dice + legal moves so the roller can choose — all server-computed.
     *
     * Pass $user = null for the spectator view: no per-player secrets
     * (no legal moves, no seat identity) are exposed.
     */
    public function stateFor(LudoMatch $match, ?User $user): array
    {
        $match->load('players');
        $me = $user ? $match->players->firstWhere('user_id', $user->id) : null;

        $legalMoves = [];
        $pendingDice = $match->pending_dice;
        if ($me && $me->status === 'playing' && (int) $match->current_turn_user_id === (int) $user->id && $pendingDice) {
            $legalMoves = $this->engine->legalMoves($match->board_state ?? [], $me->color, $pendingDice);
        }

        return [
            'id' => $match->id,
            'mode' => $match->mode,
            'bet_paise' => $match->bet_paise,
            'status' => $match->status,
            'board' => $match->board_state,
            'scores' => $match->scores,
            'players' => $this->playersPayload($match),
            'my_color' => $me?->color,
            'my_team' => $me?->team,
            'current_turn_user_id' => $match->current_turn_user_id,
            'turn_deadline_at' => $match->turn_deadline_at?->toIso8601String(),
            'ends_at' => $match->ends_at?->toIso8601String(),
            'pending_dice' => $pendingDice,
            'legal_moves' => $legalMoves,
            'winner_user_id' => $match->winner_user_id,
            'winning_team' => $match->winning_team,
        ];
    }

    /**
     * Spectator-safe state: the board, scores and players are public;
     * legal moves and seat identity are withheld.
     */
    public function publicStateFor(LudoMatch $match): array
    {
        return $this->stateFor($match, null);
    }
}
