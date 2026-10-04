<?php

namespace Tests\Feature\Ludo;

use App\Events\DiceRolled;
use App\Events\MatchFinished;
use App\Events\MatchStarted;
use App\Events\MoveApplied;
use App\Events\TurnMissed;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\Ludo\BotStrategy;
use App\Services\Ludo\LudoEngine;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Event;
use Tests\Feature\LicensedTestCase;

/**
 * Server-authoritative match flow: matchmaking, turn flow, scoring,
 * timeouts, exits, payouts, and bots. Dice are fixed or seeded — the
 * client can never influence them.
 */
class LudoMatchTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function fundedUser(int $paise = 100000): User
    {
        $user = User::factory()->create();
        $this->wallets->credit($user, 'deposit', $paise, 'seed_'.$user->id.'_'.uniqid());

        return $user;
    }

    /**
     * An engine that rolls a scripted dice sequence (then 1s).
     * Lets tests force captures, sixes, home entries, ...
     */
    private function fixedDiceEngine(array $dice): LudoEngine
    {
        return new class($dice) extends LudoEngine
        {
            public function __construct(private array $queue)
            {
                parent::__construct(null);
            }

            public function rollDice(): int
            {
                return array_shift($this->queue) ?? 1;
            }
        };
    }

    private function bindEngine(LudoEngine $engine): void
    {
        app()->instance(LudoEngine::class, $engine);
    }

    private function service(?LudoEngine $engine = null): MatchService
    {
        return new MatchService(
            app(WalletService::class),
            $engine ?? app(LudoEngine::class),
            app(BotStrategy::class),
        );
    }

    /**
     * A bot strategy that always takes the first legal move — deterministic
     * for tests (RandomBotStrategy would flake assertions).
     */
    private function firstLegalBotStrategy(): BotStrategy
    {
        return new class implements BotStrategy
        {
            public function chooseMove(array $legalMoves, array $board, string $color, int $dice): int
            {
                return $legalMoves[0];
            }
        };
    }

    /**
     * Two funded users find a 1v1/₹5 table; returns [u1, u2, match].
     * u1 (red) always has the first turn.
     */
    private function start1v1(?LudoEngine $engine = null): array
    {
        if ($engine) {
            $this->bindEngine($engine);
        }
        $service = $this->service($engine);

        $u1 = $this->fundedUser();
        $u2 = $this->fundedUser();

        $service->findOrCreateMatch($u1, '1v1', 500);
        $match = $service->findOrCreateMatch($u2, '1v1', 500);

        return [$u1, $u2, $match->fresh()];
    }

    private function playerOf(LudoMatch $match, User $user): MatchPlayer
    {
        return MatchPlayer::where('match_id', $match->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    private function setBoard(LudoMatch $match, array $board): LudoMatch
    {
        $match->update(['board_state' => $board]);

        return $match->fresh();
    }

    // ------------------------------------------------------------------
    // Matchmaking
    // ------------------------------------------------------------------

    public function test_matchmaking_pairs_two_users_and_starts_match(): void
    {
        Event::fake();

        $u1 = $this->fundedUser();
        $u2 = $this->fundedUser();
        $service = $this->service();

        $first = $service->findOrCreateMatch($u1, '1v1', 500);
        $this->assertSame('waiting', $first->status);

        $match = $service->findOrCreateMatch($u2, '1v1', 500);
        $match = $match->fresh();

        $this->assertSame($first->id, $match->id, 'Second user must join the waiting table.');
        $this->assertSame('running', $match->status);
        $this->assertCount(2, $match->players);

        // Bets debited at start, exactly once.
        $this->assertSame(99500, $this->wallets->balance($u1));
        $this->assertSame(99500, $this->wallets->balance($u2));
        $this->assertSame(1, WalletLedger::where('reference_id', "match_{$match->id}_bet_{$u1->id}")->count());

        // First turn dealt to the first seat, with a deadline.
        $this->assertSame($u1->id, (int) $match->current_turn_user_id);
        $this->assertNotNull($match->turn_deadline_at);
        $this->assertNotNull($match->ends_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes(5)->timestamp, $match->ends_at->timestamp, 5
        );

        Event::assertDispatched(MatchStarted::class);
    }

    public function test_find_rejects_unsupported_bet(): void
    {
        $user = $this->fundedUser();

        try {
            $this->service()->findOrCreateMatch($user, '1v1', 700);
            $this->fail('Expected LudoException.');
        } catch (LudoException $e) {
            $this->assertSame('INVALID_BET', $e->errorCode);
        }

        // HTTP layer also rejects it (validation).
        $response = $this->actingAs($user)->postJson('/play/find', [
            'mode' => '1v1', 'bet_paise' => 700,
        ]);
        $response->assertStatus(422);
    }

    public function test_find_rejects_insufficient_balance(): void
    {
        $user = User::factory()->create(); // empty wallet

        $response = $this->actingAs($user)->postJson('/play/find', [
            'mode' => '1v1', 'bet_paise' => 500,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INSUFFICIENT_BALANCE');
    }

    public function test_user_cannot_join_two_matches(): void
    {
        [$u1, $u2, $match] = $this->start1v1();

        $response = $this->actingAs($u1)->postJson('/play/find', [
            'mode' => '1v1', 'bet_paise' => 500,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ALREADY_IN_MATCH');
    }

    public function test_non_player_cannot_view_match(): void
    {
        [$u1, $u2, $match] = $this->start1v1();
        $stranger = $this->fundedUser();

        $this->actingAs($stranger)->getJson("/play/match/{$match->id}")
            ->assertStatus(403);

        $this->actingAs($u1)->getJson("/play/match/{$match->id}")
            ->assertOk()
            ->assertJsonPath('status', 'running');
    }

    // ------------------------------------------------------------------
    // Turn flow
    // ------------------------------------------------------------------

    public function test_roll_and_move_happy_path(): void
    {
        Event::fake();
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([6, 2]));

        // Roll a 6: token may leave base.
        $roll = $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll");
        $roll->assertOk()->assertJson(['dice' => 6, 'legal_moves' => [0, 1, 2, 3]]);

        $move = $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 0,
        ]);
        $move->assertOk();

        $match = $match->fresh();
        $this->assertSame([0, -1, -1, -1], $match->board_state['red']);
        // A 6 earns another roll — still u1's turn.
        $this->assertSame($u1->id, (int) $match->current_turn_user_id);

        $roll2 = $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll");
        $roll2->assertOk()->assertJson(['dice' => 2, 'legal_moves' => [0]]);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 0,
        ])->assertOk();

        $match = $match->fresh();
        $this->assertSame([2, -1, -1, -1], $match->board_state['red']);
        // Non-6: turn passes to u2.
        $this->assertSame($u2->id, (int) $match->current_turn_user_id);

        Event::assertDispatched(DiceRolled::class);
        Event::assertDispatched(MoveApplied::class);
    }

    public function test_roll_out_of_turn_rejected(): void
    {
        [$u1, $u2, $match] = $this->start1v1();

        $response = $this->actingAs($u2)->postJson("/play/match/{$match->id}/roll");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'NOT_YOUR_TURN');
    }

    public function test_double_roll_rejected(): void
    {
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([4, 4]));

        // 4 with everything in base -> no legal moves -> auto-pass to u2.
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk();
        $match = $match->fresh();
        $this->assertSame($u2->id, (int) $match->current_turn_user_id);

        // u2 rolls 4 -> auto-pass back; now force a real pending dice by
        // giving u1 a token out and rolling again via the service.
        $match = $this->setBoard($match, ['red' => [10, -1, -1, -1], 'green' => [-1, -1, -1, -1]]);
        $match->update(['current_turn_user_id' => $u1->id]);
        $this->service($this->fixedDiceEngine([3]))->roll($match->fresh(), $u1);

        $response = $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll");
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ALREADY_ROLLED');
    }

    public function test_move_without_dice_rejected(): void
    {
        [$u1, $u2, $match] = $this->start1v1();

        $response = $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'NO_DICE');
    }

    public function test_illegal_move_rejected_and_state_unchanged(): void
    {
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([3]));
        $match = $this->setBoard($match, ['red' => [0, -1, -1, -1], 'green' => [-1, -1, -1, -1]]);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")
            ->assertOk()->assertJson(['dice' => 3, 'legal_moves' => [0]]);

        // Token 1 is in base and the dice is 3 -> illegal.
        $response = $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 1,
        ]);
        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'ILLEGAL_MOVE');

        // Nothing changed: board, pending dice, and turn all intact.
        $match = $match->fresh();
        $this->assertSame([0, -1, -1, -1], $match->board_state['red']);
        $this->assertSame(3, $match->pending_dice);
        $this->assertSame($u1->id, (int) $match->current_turn_user_id);
        $this->assertSame(0, $this->playerOf($match, $u1)->score);
    }

    // ------------------------------------------------------------------
    // Scoring
    // ------------------------------------------------------------------

    public function test_capture_scores_attacker_plus15_victim_minus15(): void
    {
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([1]));
        // Red 9 -> 10 (abs 10); green at 49 -> abs (13+49)%52 = 10. Not safe.
        $match = $this->setBoard($match, ['red' => [9, -1, -1, -1], 'green' => [49, -1, -1, -1]]);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk();
        $move = $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 0,
        ]);
        $move->assertOk();
        $this->assertContains('capture', array_column($move->json('events'), 'type'));

        $match = $match->fresh();
        $this->assertSame(-1, $match->board_state['green'][0], 'Victim returns to base.');
        $this->assertSame(15, $this->playerOf($match, $u1)->score);
        $this->assertSame(-15, $this->playerOf($match, $u2)->score);
        $this->assertSame(15, $match->scores[(string) $u1->id]);
        $this->assertSame(-15, $match->scores[(string) $u2->id]);
    }

    public function test_exact_roll_home_entry_scores_plus10(): void
    {
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([2]));
        $match = $this->setBoard($match, ['red' => [54, -1, -1, -1], 'green' => [-1, -1, -1, -1]]);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk();
        $move = $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 0,
        ]);
        $move->assertOk();
        $this->assertContains('home', array_column($move->json('events'), 'type'));

        $match = $match->fresh();
        $this->assertSame(56, $match->board_state['red'][0]);
        $this->assertSame(10, $this->playerOf($match, $u1)->score);
        $this->assertSame('playing', $this->playerOf($match, $u1)->status);
    }

    public function test_finishing_all_tokens_awards_win_bonus(): void
    {
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([2]));
        $match = $this->setBoard($match, ['red' => [56, 56, 56, 54], 'green' => [-1, -1, -1, -1]]);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk();
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", [
            'token_index' => 3,
        ])->assertOk();

        $player = $this->playerOf($match, $u1);
        $this->assertSame('finished', $player->status);
        // +10 home entry, +50 win bonus.
        $this->assertSame(60, $player->score);
        // u2 still playing -> match continues, turn passes to u2.
        $this->assertSame('running', $match->fresh()->status);
        $this->assertSame($u2->id, (int) $match->fresh()->current_turn_user_id);
    }

    public function test_three_consecutive_sixes_forfeit_turn(): void
    {
        Event::fake();
        [$u1, $u2, $match] = $this->start1v1($this->fixedDiceEngine([6, 6, 6]));

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk(); // six #1
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", ['token_index' => 0])->assertOk();
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")->assertOk(); // six #2
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/move", ['token_index' => 0])->assertOk();

        $forfeit = $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll"); // six #3
        $forfeit->assertOk()->assertJson(['dice' => 6, 'forfeited' => true]);

        $match = $match->fresh();
        $this->assertNull($match->pending_dice);
        $this->assertSame(0, $match->consecutive_sixes);
        $this->assertSame($u2->id, (int) $match->current_turn_user_id);

        Event::assertDispatched(TurnMissed::class, fn (TurnMissed $e) => $e->reason === 'forfeit');
    }

    // ------------------------------------------------------------------
    // Tick: timeouts and the match clock
    // ------------------------------------------------------------------

    public function test_turn_timeout_misses_then_removes_at_five(): void
    {
        Event::fake();
        [$u1, $u2, $match] = $this->start1v1();

        for ($i = 1; $i <= 5; $i++) {
            $match->update([
                'current_turn_user_id' => $u1->id,
                'turn_deadline_at' => now()->subSecond(),
                'pending_dice' => null,
            ]);
            $this->artisan('matches:tick')->assertSuccessful();
            $match = $match->fresh();
            if ($i < 5) {
                $this->assertSame('running', $match->status);
                $this->assertSame($i, $this->playerOf($match, $u1)->missed_turns);
            }
        }

        $this->assertSame(5, $this->playerOf($match, $u1)->missed_turns);
        $this->assertSame('lost', $this->playerOf($match, $u1)->status);
        // Only team 1 remains -> match settled, u2 wins by score (0 vs 0
        // tie broken to the lower seat -> u1... but u1 lost, so u2).
        $this->assertSame('finished', $match->status);
        $this->assertSame($u2->id, (int) $match->winner_user_id);

        Event::assertDispatched(TurnMissed::class, fn (TurnMissed $e) => $e->reason === 'timeout');
        Event::assertDispatched(MatchFinished::class);
    }

    public function test_match_timer_expiry_crowns_highest_score(): void
    {
        Event::fake();
        [$u1, $u2, $match] = $this->start1v1();

        $this->playerOf($match, $u1)->update(['score' => 100]);
        $this->playerOf($match, $u2)->update(['score' => 50]);
        $match->update([
            'scores' => [(string) $u1->id => 100, (string) $u2->id => 50],
            'ends_at' => now()->subSecond(),
        ]);

        $this->artisan('matches:tick')->assertSuccessful();

        $match = $match->fresh();
        $this->assertSame('finished', $match->status);
        $this->assertSame($u1->id, (int) $match->winner_user_id);
        $this->assertSame(0, (int) $match->winning_team);

        // Pot 1000 (2 x ₹5); commission 10% = 100; winner takes 900.
        $this->assertSame(100400, $this->wallets->balance($u1));
        $this->assertSame(99500, $this->wallets->balance($u2));
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertSame(100, $this->wallets->balance($platform));

        // Ledger balances: every paise is accounted for.
        $total = (int) WalletLedger::sum('amount_paise');
        $this->assertSame(200000, $total, 'Deposits in must equal payouts + commission out.');

        Event::assertDispatched(MatchFinished::class);
    }

    public function test_finish_is_idempotent(): void
    {
        [$u1, $u2, $match] = $this->start1v1();
        $service = $this->service();

        $match->update(['ends_at' => now()->subSecond()]);
        $service->finishMatch($match->fresh());
        $afterFirst = [$this->wallets->balance($u1), $this->wallets->balance($u2)];

        // A retried tick / double call must not move money twice.
        $service->finishMatch($match->fresh());
        $service->tick();

        $this->assertSame($afterFirst, [$this->wallets->balance($u1), $this->wallets->balance($u2)]);
        $this->assertSame(1, WalletLedger::where('reference_id', "match_{$match->id}_commission")->count());
    }

    // ------------------------------------------------------------------
    // Exit
    // ------------------------------------------------------------------

    public function test_exit_forfeits_bet_and_ends_1v1(): void
    {
        Event::fake();
        [$u1, $u2, $match] = $this->start1v1();

        $response = $this->actingAs($u1)->postJson("/play/match/{$match->id}/exit");
        $response->assertOk()->assertJson(['exited' => true]);

        $match = $match->fresh();
        $this->assertSame('lost', $this->playerOf($match, $u1)->status);
        // Bet is NOT refunded.
        $this->assertSame(99500, $this->wallets->balance($u1));
        // All humans of team 0 lost -> match ends, u2 wins by score.
        $this->assertSame('finished', $match->status);
        $this->assertSame($u2->id, (int) $match->winner_user_id);

        Event::assertDispatched(MatchFinished::class);
    }

    public function test_exit_from_waiting_lobby_frees_seat_without_charge(): void
    {
        $u1 = $this->fundedUser();
        $match = $this->service()->findOrCreateMatch($u1, '1v1', 500);
        $this->assertSame('waiting', $match->status);

        $this->actingAs($u1)->postJson("/play/match/{$match->id}/exit")
            ->assertOk();

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
        $this->assertSame('cancelled', $match->fresh()->status);
        $this->assertSame(100000, $this->wallets->balance($u1), 'No bet was ever debited.');
    }

    // ------------------------------------------------------------------
    // Bots
    // ------------------------------------------------------------------

    public function test_bot_takes_its_turn_automatically(): void
    {
        Event::fake();
        // Scripted dice: human rolls 3 (auto-pass, all in base), bot rolls
        // 6 (leaves base) then 2 (advances to step 2).
        $this->bindEngine($this->fixedDiceEngine([3, 6, 2]));
        app()->instance(BotStrategy::class, $this->firstLegalBotStrategy());

        $u1 = $this->fundedUser();
        $service = $this->service();

        $match = LudoMatch::create(['mode' => '1v1', 'bet_paise' => 500, 'status' => 'waiting']);
        $match->players()->create(['user_id' => $u1->id, 'team' => 0, 'color' => 'red']);
        $bot = $match->players()->create([
            'user_id' => null, 'team' => 1, 'color' => 'green', 'is_bot' => true,
        ]);

        $service->startMatch($match->fresh());
        $match = $match->fresh();
        $this->assertSame('running', $match->status);
        $this->assertSame($u1->id, (int) $match->current_turn_user_id);

        // Human rolls 3 -> nothing can move -> turn auto-passes to the bot,
        // which plays its whole turn instantly on the server.
        $this->actingAs($u1)->postJson("/play/match/{$match->id}/roll")
            ->assertOk()->assertJson(['auto_passed' => true]);

        $match = $match->fresh();
        $this->assertSame([2, -1, -1, -1], $match->board_state['green'], 'Bot moved on its own.');
        $this->assertSame($u1->id, (int) $match->current_turn_user_id, 'Turn returned to the human.');
        // Bots never touch the wallet.
        $this->assertSame(99500, $this->wallets->balance($u1));
        $this->assertSame(0, WalletLedger::where('user_id', $bot->user_id)->count());

        Event::assertDispatched(MoveApplied::class);
    }

    public function test_seeded_bots_exist(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\LudoSeeder'])
            ->assertSuccessful();

        for ($i = 1; $i <= 5; $i++) {
            $bot = User::where('username', "bot_{$i}")->first();
            $this->assertNotNull($bot, "bot_{$i} missing.");
            $this->assertSame('bot', $bot->role);
        }

        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertSame('system', $platform->role);
    }
}
