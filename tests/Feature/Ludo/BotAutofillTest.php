<?php

namespace Tests\Feature\Ludo;

use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\Ludo\MatchService;
use App\Services\WalletService;
use Tests\Feature\LicensedTestCase;

/**
 * Bot auto-fill: a public waiting table that has waited longer than
 * bot_join_after_seconds gets a bot seated (up to bot_max_per_match),
 * playing with the configured difficulty. Bots never touch wallets.
 */
class BotAutofillTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected MatchService $matches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
        $this->matches = app(MatchService::class);

        Setting::set('bot_fill_enabled', '1');
        Setting::set('bot_tables', '["500"]');
        Setting::set('bot_max_per_match', '1');
        Setting::set('bot_join_after_seconds', '1');
        Setting::set('bot_difficulty', 'hard');
    }

    private function fundedUser(int $paise = 100000): User
    {
        $user = User::factory()->create();
        $this->wallets->credit($user, 'deposit', $paise, 'seed_'.$user->id.'_'.uniqid());

        return $user;
    }

    private function botUser(string $username = 'bot_1'): User
    {
        return User::factory()->create([
            'username' => $username,
            'role' => 'bot',
            'status' => 'active',
        ]);
    }

    public function test_bot_fills_waiting_match_after_timeout(): void
    {
        $bot = $this->botUser();
        $user = $this->fundedUser();

        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);
        $this->assertSame('waiting', $match->status);
        // Pretend the table has been waiting past the timeout.
        $match->forceFill(['created_at' => now()->subSeconds(5)])->save();

        $this->matches->tick();

        $match = $match->fresh();
        $this->assertSame('running', $match->status, 'bot should complete the 1v1 table');

        $botSeat = $match->players()->where('is_bot', true)->first();
        $this->assertNotNull($botSeat, 'a bot seat must exist');
        $this->assertSame($bot->id, (int) $botSeat->user_id);
        $this->assertSame('hard', $botSeat->bot_difficulty);

        // Bots never debit/credit wallets — the existing rule.
        $this->assertSame(
            0,
            WalletLedger::where('user_id', $bot->id)->count(),
            'bot must have zero wallet ledger rows'
        );

        // The human's bet WAS debited when the filled table started.
        $this->assertSame(100000 - 500, $this->wallets->balance($user));
    }

    public function test_no_fill_before_timeout(): void
    {
        $this->botUser();
        $user = $this->fundedUser();

        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);
        // created_at is now — inside the 1s... use a longer timeout.
        Setting::set('bot_join_after_seconds', '3600');

        $this->matches->tick();

        $this->assertSame('waiting', $match->fresh()->status);
        $this->assertSame(0, $match->players()->where('is_bot', true)->count());
    }

    public function test_no_fill_when_disabled(): void
    {
        Setting::set('bot_fill_enabled', '0');
        $this->botUser();
        $user = $this->fundedUser();

        $match = $this->matches->findOrCreateMatch($user, '1v1', 500);
        $match->forceFill(['created_at' => now()->subHour()])->save();

        $this->matches->tick();

        $this->assertSame('waiting', $match->fresh()->status);
    }

    public function test_no_fill_on_disallowed_table(): void
    {
        // Bots only allowed on ₹5 tables here; this is a ₹10 table.
        $this->botUser();
        $user = $this->fundedUser();

        $match = $this->matches->findOrCreateMatch($user, '1v1', 1000);
        $match->forceFill(['created_at' => now()->subHour()])->save();

        $this->matches->tick();

        $this->assertSame('waiting', $match->fresh()->status);
    }

    public function test_max_bots_per_match_respected(): void
    {
        Setting::set('bot_max_per_match', '1');
        $this->botUser('bot_1');
        $this->botUser('bot_2');
        $user = $this->fundedUser();

        // 2v2 needs 4 seats: 1 human + up to 1 bot (max), then it must
        // stay waiting instead of filling the rest with bots.
        $match = $this->matches->findOrCreateMatch($user, '2v2', 500);
        $match->forceFill(['created_at' => now()->subSeconds(5)])->save();

        $this->matches->tick();
        $this->matches->tick(); // second tick must not add another bot

        $match = $match->fresh();
        $this->assertSame('waiting', $match->status);
        $this->assertSame(1, $match->players()->where('is_bot', true)->count());
    }

    public function test_private_match_never_gets_bot_fill(): void
    {
        $this->botUser();
        $inviter = $this->fundedUser();
        $friend = $this->fundedUser();

        $match = $this->matches->createPrivateMatch($inviter, $friend, 500);
        $match->forceFill(['created_at' => now()->subHour()])->save();

        $this->matches->tick();

        $this->assertSame('waiting', $match->fresh()->status);
        $this->assertSame(0, $match->players()->where('is_bot', true)->count());
    }
}
