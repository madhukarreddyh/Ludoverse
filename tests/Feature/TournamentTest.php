<?php

namespace Tests\Feature;

use App\Models\LudoMatch;
use App\Models\Setting;
use App\Models\Tournament;
use App\Models\TournamentFixture;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\Ludo\MatchService;
use App\Services\TournamentService;
use App\Services\WalletService;

/**
 * Tournament engine end to end (1v1, 4 players):
 * register -> league round-robin -> points -> Qualifier 1 / Eliminator
 * -> Qualifier 2 -> Final -> prizes, with the ledger balancing to zero.
 * Plus: no-show join deadlines and 4v4 minimum-3 enforcement.
 */
class TournamentTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected MatchService $matches;

    protected TournamentService $tournaments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
        $this->matches = app(MatchService::class);
        $this->tournaments = app(TournamentService::class);
        Setting::set('tournament_commission_rate', '10');
    }

    private function fundedUser(int $paise = 100000): User
    {
        $user = User::factory()->create();
        $this->wallets->credit($user, 'deposit', $paise, 'seed_'.$user->id.'_'.uniqid());

        return $user;
    }

    private function makeTournament(string $mode, int $entryFee, int $max): Tournament
    {
        return $this->tournaments->createTournament([
            'name' => 'Test Cup',
            'mode' => $mode,
            'entry_fee_paise' => $entryFee,
            'max_participants' => $max,
        ]);
    }

    /**
     * Both sides join the fixture, then the winner is forced by score
     * and the real finishMatch pipeline records the result.
     */
    private function winFixture(TournamentFixture $fixture, User $winner): void
    {
        $p1 = $fixture->participant1->user;
        $p2 = $fixture->participant2->user;

        $this->tournaments->joinFixture($fixture, $p1);
        $this->tournaments->joinFixture($fixture, $p2);

        $fixture = $fixture->fresh();
        $this->assertNotNull($fixture->match_id, 'fixture match should be created');
        $this->assertSame('ongoing', $fixture->status);

        $match = LudoMatch::findOrFail($fixture->match_id);
        $this->assertSame('running', $match->status);
        $this->assertSame(0, $match->bet_paise, 'fixture matches run bet 0');

        foreach ($match->players as $mp) {
            $mp->update(['score' => (int) $mp->user_id === (int) $winner->id ? 100 : 0]);
        }
        $this->matches->finishMatch($match->fresh());

        $this->assertSame('completed', $fixture->fresh()->status);
    }

    private function participantOf(Tournament $t, User $u)
    {
        return $t->participants()->where('user_id', $u->id)->firstOrFail();
    }

    // ------------------------------------------------------------------
    // Full 1v1 lifecycle
    // ------------------------------------------------------------------

    public function test_full_1v1_lifecycle_with_prizes(): void
    {
        [$u1, $u2, $u3, $u4] = [
            $this->fundedUser(), $this->fundedUser(),
            $this->fundedUser(), $this->fundedUser(),
        ];

        $t = $this->makeTournament('1v1', 10000, 4);

        foreach ([$u1, $u2, $u3, $u4] as $u) {
            $this->tournaments->register($t, $u);
            $this->assertSame(90000, $this->wallets->balance($u), 'entry fee debited');
        }
        $this->assertTrue(
            $this->wallets->referenceExists("tournament_{$t->id}_entry_{$u1->id}")
        );

        $this->tournaments->startLeague($t);
        $this->assertSame('league', $t->fresh()->status);
        $this->assertSame(6, $t->fixtures()->where('stage', 'league')->count());

        // Round-robin: participant1 always wins.
        // u1: 3 wins (6pts), u2: beats u3,u4 (4pts), u3: beats u4 (2pts).
        foreach ($t->fixtures()->where('stage', 'league')->orderBy('id')->get() as $f) {
            $this->winFixture($f, $f->participant1->user);
        }

        $pts = fn (User $u) => (int) $this->participantOf($t, $u)->points;
        $this->assertSame(6, $pts($u1));
        $this->assertSame(4, $pts($u2));
        $this->assertSame(2, $pts($u3));
        $this->assertSame(0, $pts($u4));

        // Top 4 -> Q1 (u1 vs u2), Eliminator (u3 vs u4).
        $this->tournaments->advanceStage($t);
        $this->assertSame('qualifier', $t->fresh()->status);

        $q1 = $t->fixtures()->where('stage', 'qualifier1')->firstOrFail();
        $el = $t->fixtures()->where('stage', 'eliminator')->firstOrFail();
        $this->assertSame(
            [$this->participantOf($t, $u1)->id, $this->participantOf($t, $u2)->id],
            [(int) $q1->participant1_id, (int) $q1->participant2_id]
        );

        $this->winFixture($q1, $u1); // u1 -> final
        $this->winFixture($el, $u3); // u3 -> qualifier 2, u4 eliminated
        $this->assertSame('eliminated', $this->participantOf($t, $u4)->fresh()->status);

        // Q2: u2 (Q1 loser) vs u3 (Eliminator winner) -> u3 wins.
        $this->tournaments->advanceStage($t);
        $q2 = $t->fixtures()->where('stage', 'qualifier2')->firstOrFail();
        $this->winFixture($q2, $u3);

        // Final: u1 vs u3 -> u1 champion.
        $this->tournaments->advanceStage($t);
        $this->assertSame('final', $t->fresh()->status);
        $final = $t->fixtures()->where('stage', 'final')->firstOrFail();
        $this->winFixture($final, $u1);

        $this->tournaments->complete($t);
        $this->assertSame('completed', $t->fresh()->status);

        // Pool 40000 - 10% commission = 36000.
        // Champion 60% = 21600, runner-up 25% = 9000, third 15% = 5400.
        $this->assertSame(100000 - 10000 + 21600, $this->wallets->balance($u1));
        $this->assertSame(100000 - 10000 + 9000, $this->wallets->balance($u3));
        $this->assertSame(100000 - 10000 + 5400, $this->wallets->balance($u2));
        $this->assertSame(100000 - 10000, $this->wallets->balance($u4));

        // Ledger balanced: every tournament reference nets to zero
        // (entry debits + prize credits + platform commission share).
        $sum = WalletLedger::where('reference_id', 'like', "tournament_{$t->id}_%")->sum('amount_paise');
        $this->assertSame(0, (int) $sum, 'tournament ledger must balance');
    }

    public function test_registration_validation(): void
    {
        $t = $this->makeTournament('1v1', 10000, 4);
        $u = $this->fundedUser(500); // can't afford the fee

        try {
            $this->tournaments->register($t, $u);
            $this->fail('expected INSUFFICIENT_BALANCE');
        } catch (\App\Services\Ludo\LudoException $e) {
            $this->assertSame('INSUFFICIENT_BALANCE', $e->errorCode);
        }

        $rich = $this->fundedUser();
        $this->tournaments->register($t, $rich);
        try {
            $this->tournaments->register($t, $rich);
            $this->fail('expected ALREADY_REGISTERED');
        } catch (\App\Services\Ludo\LudoException $e) {
            $this->assertSame('ALREADY_REGISTERED', $e->errorCode);
        }
    }

    // ------------------------------------------------------------------
    // No-shows
    // ------------------------------------------------------------------

    public function test_no_show_deadline_records_loss(): void
    {
        $users = [$this->fundedUser(), $this->fundedUser(), $this->fundedUser(), $this->fundedUser()];
        $t = $this->makeTournament('1v1', 0, 4);
        foreach ($users as $u) {
            $this->tournaments->register($t, $u);
        }
        $this->tournaments->startLeague($t);

        // Fixture 1: nobody joins. Fixture 2: only participant1 joins.
        $fixtures = $t->fixtures()->where('stage', 'league')->orderBy('id')->take(2)->get();
        [$f1, $f2] = [$fixtures[0], $fixtures[1]];

        $this->tournaments->joinFixture($f2, $f2->participant1->user);

        foreach ([$f1, $f2] as $f) {
            $f->forceFill(['join_deadline_at' => now()->subMinute()])->save();
        }
        $this->tournaments->processJoinDeadlines();

        // Nobody showed on f1: both take a loss, no points from f1.
        // (u1's participant is also participant1 on f2, where u1 earned
        // the walkover win, so expect his combined totals.)
        $f1 = $f1->fresh();
        $this->assertSame('completed', $f1->status);
        $this->assertSame(1, (int) $f1->participant1->fresh()->losses);
        $this->assertSame(1, (int) $f1->participant2->fresh()->losses);
        $this->assertSame(0, (int) $f1->participant2->fresh()->points);

        // Only u1 showed on f2: walkover win for u1, loss for u3.
        $f2 = $f2->fresh();
        $this->assertSame('completed', $f2->status);
        $p1 = $this->participantOf($t, $users[0])->fresh();
        $this->assertSame(2, (int) $p1->points);
        $this->assertSame(1, (int) $p1->wins);
        $this->assertSame(1, (int) $p1->losses); // from the f1 no-show
        $p3 = $this->participantOf($t, $users[2])->fresh();
        $this->assertSame(1, (int) $p3->losses);
        $this->assertSame(0, (int) $p3->points);
        $this->assertSame(
            $f2->participant1_id,
            (int) $f2->winner_participant_id
        );
    }

    // ------------------------------------------------------------------
    // 4v4 minimum-3 enforcement
    // ------------------------------------------------------------------

    public function test_4v4_side_with_fewer_than_3_forfeits(): void
    {
        $users = [];
        for ($i = 0; $i < 16; $i++) {
            $users[] = $this->fundedUser();
        }
        // Bot users for padding the played fixture later.
        for ($i = 1; $i <= 4; $i++) {
            User::factory()->create(['username' => "tbot_{$i}", 'role' => 'bot', 'status' => 'active']);
        }

        $t = $this->makeTournament('4v4', 0, 16);
        foreach ($users as $u) {
            $this->tournaments->register($t, $u);
        }
        $this->tournaments->startLeague($t);
        $this->assertSame(2, $t->fixtures()->where('stage', 'league')->count());

        $fixtures = $t->fixtures()->where('stage', 'league')->orderBy('id')->get();
        [$f1, $f2] = [$fixtures[0], $fixtures[1]];

        // Fixture 1: side 1 confirms 2 (< 3 -> forfeit), side 2 confirms 3.
        $s1 = $f1->sideUserIds(1);
        $s2 = $f1->sideUserIds(2);
        $this->tournaments->joinFixture($f1, User::find($s1[0]), 1);
        $this->tournaments->joinFixture($f1, User::find($s1[1]), 1);
        $this->tournaments->joinFixture($f1, User::find($s2[0]), 2);
        $this->tournaments->joinFixture($f1, User::find($s2[1]), 2);
        $this->tournaments->joinFixture($f1, User::find($s2[2]), 2);
        $f1->forceFill(['join_deadline_at' => now()->subMinute()])->save();

        $this->tournaments->processJoinDeadlines();

        $f1 = $f1->fresh();
        $this->assertSame('completed', $f1->status);
        $this->assertNull($f1->match_id, 'forfeited fixtures create no match');
        $this->assertSame(2, (int) $f1->winner_side);

        // Winning side: every rostered user gets 2 pts + a win.
        foreach ($f1->sideUserIds(2) as $uid) {
            $p = $t->participants()->where('user_id', $uid)->firstOrFail();
            $this->assertSame(2, (int) $p->points);
            $this->assertSame(1, (int) $p->wins);
        }
        // Forfeiting side: every rostered user takes a loss.
        foreach ($f1->sideUserIds(1) as $uid) {
            $p = $t->participants()->where('user_id', $uid)->firstOrFail();
            $this->assertSame(1, (int) $p->losses);
        }

        // Fixture 2: both sides confirm 3+ -> real 4v4 match, bots pad.
        $g1 = $f2->sideUserIds(1);
        $g2 = $f2->sideUserIds(2);
        foreach (array_slice($g1, 0, 3) as $uid) {
            $this->tournaments->joinFixture($f2, User::find($uid), 1);
        }
        foreach ($g2 as $uid) {
            $this->tournaments->joinFixture($f2, User::find($uid), 2);
        }
        $f2->forceFill(['join_deadline_at' => now()->subMinute()])->save();
        $this->tournaments->processJoinDeadlines();

        $f2 = $f2->fresh();
        $this->assertSame('ongoing', $f2->status);
        $this->assertNotNull($f2->match_id);
        $match = LudoMatch::findOrFail($f2->match_id);
        $this->assertSame('running', $match->status);
        $this->assertSame(8, $match->players()->count(), '8 seats: 7 humans + 1 bot pad');
        $this->assertSame(1, $match->players()->where('is_bot', true)->count());
    }
}
