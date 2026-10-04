<?php

namespace Tests\Feature\Ludo;

use App\Models\LudoMatch;
use App\Models\User;
use App\Services\Ludo\MatchService;
use App\Services\WalletService;
use Tests\Feature\LicensedTestCase;

/**
 * Spectator: anyone (even logged out) can VIEW a match's public state,
 * but only seated players may roll/move (403 for everyone else).
 */
class SpectatorTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected MatchService $matches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
        $this->matches = app(MatchService::class);
    }

    private function fundedUser(): User
    {
        $user = User::factory()->create();
        $this->wallets->credit($user, 'deposit', 100000, 'seed_'.$user->id.'_'.uniqid());

        return $user;
    }

    private function runningMatch(): LudoMatch
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($a, '1v1', 500);
        // Second player takes the open seat -> the table starts.
        $match = $this->matches->findOrCreateMatch($b, '1v1', 500);

        return $match->fresh();
    }

    public function test_guest_can_watch_public_state(): void
    {
        $match = $this->runningMatch();

        $resp = $this->getJson("/play/match/{$match->id}/watch")->assertOk();

        // Public state: board, scores, status, players — and an EMPTY
        // legal_moves list (no move options are disclosed to spectators).
        $this->assertSame('running', $resp->json('status'));
        $this->assertArrayHasKey('board', $resp->json());
        $this->assertArrayHasKey('scores', $resp->json());
        $this->assertSame([], $resp->json('legal_moves'));
        $this->assertNull($resp->json('my_color'));
    }

    public function test_spectator_sees_running_scores_but_cannot_play(): void
    {
        $match = $this->runningMatch();
        $spectator = $this->fundedUser();

        // View works for an authenticated non-seated user too.
        $this->actingAs($spectator)
            ->getJson("/play/match/{$match->id}/watch")
            ->assertOk()
            ->assertJsonPath('status', 'running');

        // ...but roll and move are forbidden for non-seated users.
        $this->actingAs($spectator)
            ->postJson("/play/match/{$match->id}/roll")
            ->assertForbidden();

        $this->actingAs($spectator)
            ->postJson("/play/match/{$match->id}/move", ['token' => 0])
            ->assertForbidden();

        // Guests can't roll/move either (denied without a seat).
        $this->postJson("/play/match/{$match->id}/roll")->assertForbidden();
        $this->postJson("/play/match/{$match->id}/move", ['token' => 0])->assertForbidden();
    }

    public function test_seated_player_can_still_roll(): void
    {
        $match = $this->runningMatch();
        $seated = $match->players()->orderBy('id')->first()->user;

        $this->actingAs($seated)
            ->postJson("/play/match/{$match->id}/roll")
            ->assertOk();
    }

    public function test_watch_shows_finished_match_result(): void
    {
        $match = $this->runningMatch();
        foreach ($match->players as $mp) {
            $mp->update(['score' => $mp->seat === 0 ? 100 : 0]);
        }
        $this->matches->finishMatch($match->fresh());

        $this->getJson("/play/match/{$match->id}/watch")
            ->assertOk()
            ->assertJsonPath('status', 'finished');
    }
}
