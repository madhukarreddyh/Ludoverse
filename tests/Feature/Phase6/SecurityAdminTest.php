<?php

namespace Tests\Feature\Phase6;

use App\Models\AdminNotice;
use App\Models\ApiKey;
use App\Models\ApiKeyLog;
use App\Models\BannedDevice;
use App\Models\BonusLock;
use App\Models\CheatLog;
use App\Models\Device;
use App\Models\FraudFlag;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerToken;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\CheatDetectionService;
use App\Services\DeviceService;
use App\Services\EmbedTokenService;
use App\Services\FraudScanService;
use App\Services\HeuristicVpnCheck;
use App\Services\Ludo\LudoException;
use App\Services\Ludo\MatchService;
use App\Services\RiskService;
use App\Services\TableManager;
use App\Services\WalletFrozenException;
use App\Services\WalletService;
use App\Services\InsufficientBalanceException;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\LicensedTestCase;

/**
 * Phase 6 — security + admin. Covers every QA-law contract:
 * device security, anti-cheat, anti-collusion, bonus locks, liquidity,
 * risk manager, maintenance mode, API auth, docs, chatbot, embed tokens.
 */
class SecurityAdminTest extends LicensedTestCase
{
    protected WalletService $wallets;

    protected MatchService $matches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallets = app(WalletService::class);
        $this->matches = app(MatchService::class);
    }

    private function fundedUser(int $paise = 100000, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        if ($paise > 0) {
            $this->wallets->credit($user, 'deposit', $paise, 'seed_'.$user->id.'_'.uniqid());
        }

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function deviceHash(string $seed): string
    {
        return hash('sha256', 'device-'.$seed);
    }

    private function signupPayload(string $username, ?string $deviceHash): array
    {
        return [
            'name' => 'Test User',
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1', 'privacy' => '1', 'refund' => '1',
            'device_hash' => $deviceHash,
        ];
    }

    // ------------------------------------------------------------------
    // 1. Device security
    // ------------------------------------------------------------------

    public function test_duplicate_device_signup_blocked(): void
    {
        $hash = $this->deviceHash('shared');

        $this->post('/signup', $this->signupPayload('alice1', $hash))->assertRedirect();
        $this->assertDatabaseHas('devices', [
            'user_id' => User::where('username', 'alice1')->first()->id,
            'device_hash' => $hash,
        ]);
        $this->post('/logout'); // signup routes are guest-only

        // Second account on the same device is rejected.
        $response = $this->post('/signup', $this->signupPayload('bob1', $hash));
        $response->assertSessionHasErrors('device');
        $this->assertDatabaseMissing('users', ['username' => 'bob1']);
    }

    public function test_duplicate_device_login_blocked(): void
    {
        $hash = $this->deviceHash('shared-login');

        $alice = $this->fundedUser(0, ['username' => 'alice2']);
        Device::create([
            'user_id' => $alice->id, 'device_hash' => $hash,
            'created_at' => now(),
        ]);

        $bob = User::factory()->create(['username' => 'bob2', 'password' => bcrypt('secret123')]);

        $response = $this->post('/login', [
            'login' => 'bob2', 'password' => 'secret123', 'device_hash' => $hash,
        ]);
        $response->assertSessionHasErrors('device');
        $this->assertGuest();
    }

    public function test_banned_device_blocked(): void
    {
        $hash = $this->deviceHash('banned');

        BannedDevice::create(['device_hash' => $hash, 'reason' => 'test ban']);

        $this->post('/signup', $this->signupPayload('carol1', $hash))
            ->assertSessionHasErrors('device');
        $this->assertDatabaseMissing('users', ['username' => 'carol1']);
    }

    public function test_device_linked_on_login(): void
    {
        $hash = $this->deviceHash('fresh');
        $user = User::factory()->create(['username' => 'dave1', 'password' => bcrypt('secret123')]);

        $this->post('/login', [
            'login' => 'dave1', 'password' => 'secret123', 'device_hash' => $hash,
        ])->assertRedirect();

        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id, 'device_hash' => $hash,
        ]);
    }

    // ------------------------------------------------------------------
    // 2. Anti-cheat
    // ------------------------------------------------------------------

    public function test_impossible_score_creates_cheat_log(): void
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($a, '1v1', 500);
        $match = $this->matches->findOrCreateMatch($b, '1v1', 500);
        $this->assertSame('running', $match->status);

        // Impossible: way above the theoretical max (~390).
        $match->players()->where('user_id', $a->id)->update(['score' => 99999]);

        $this->matches->finishMatch($match);

        $this->assertDatabaseHas('cheat_logs', [
            'user_id' => $a->id,
            'match_id' => $match->id,
            'type' => 'impossible_score',
        ]);
        $this->assertSame('active', $a->fresh()->status, 'one flag must not suspend');
    }

    public function test_three_flags_in_24h_auto_suspend_and_ban_device(): void
    {
        $user = $this->fundedUser();
        $hash = $this->deviceHash('cheater');
        Device::create(['user_id' => $user->id, 'device_hash' => $hash, 'created_at' => now()]);

        $cheat = app(CheatDetectionService::class);
        $cheat->flag($user, 'impossible_move_timing');
        $cheat->flag($user->fresh(), 'client_version_mismatch');
        $this->assertSame('active', $user->fresh()->status);

        $cheat->flag($user->fresh(), 'impossible_score');

        $this->assertSame('suspended', $user->fresh()->status);
        $this->assertDatabaseHas('banned_devices', ['device_hash' => $hash]);
    }

    public function test_client_version_mismatch_flags_but_does_not_block(): void
    {
        $user = $this->fundedUser();

        $response = $this->actingAs($user)
            ->withHeaders(['X-Client-Version' => '9.9.9-wrong'])
            ->postJson('/play/find', ['mode' => '1v1', 'bet_paise' => 500]);

        // Request still goes through (waiting match created)…
        $response->assertOk();
        // …but a cheat flag was logged.
        $this->assertDatabaseHas('cheat_logs', [
            'user_id' => $user->id,
            'type' => 'client_version_mismatch',
        ]);
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_impossible_move_timing_is_logged(): void
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($a, '1v1', 500);
        $match = $this->matches->findOrCreateMatch($b, '1v1', 500);
        $match = $match->fresh();

        // It's player A's turn (first seat).
        $this->assertSame($a->id, (int) $match->current_turn_user_id);

        $rolled = $this->matches->roll($match->fresh(), $a);
        if ($rolled['legal_moves'] === []) {
            $this->markTestSkipped('no legal moves after roll — timing check needs a move');
        }

        // Move immediately: far below the 200ms human threshold.
        $this->matches->move($match->fresh(), $a, $rolled['legal_moves'][0]);

        $this->assertDatabaseHas('cheat_logs', [
            'user_id' => $a->id,
            'type' => 'impossible_move_timing',
        ]);
    }

    // ------------------------------------------------------------------
    // 3. Anti-collusion
    // ------------------------------------------------------------------

    public function test_fraud_scan_flags_same_ip_match(): void
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();

        $match = $this->matches->findOrCreateMatch($a, '1v1', 500, ['ip_address' => '9.9.9.9']);
        $this->matches->findOrCreateMatch($b, '1v1', 500, ['ip_address' => '9.9.9.9']);
        $this->assertSame('running', $match->fresh()->status);

        Artisan::call('fraud:scan');

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $a->id, 'type' => 'same_ip_match', 'status' => 'open',
        ]);
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $b->id, 'type' => 'same_ip_match', 'status' => 'open',
        ]);
    }

    public function test_fraud_scan_flags_same_device_match(): void
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();
        $hash = $this->deviceHash('colluders');

        $this->matches->findOrCreateMatch($a, '1v1', 500, ['ip_address' => '1.1.1.1', 'device_hash' => $hash]);
        $this->matches->findOrCreateMatch($b, '1v1', 500, ['ip_address' => '2.2.2.2', 'device_hash' => $hash]);

        Artisan::call('fraud:scan');

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $a->id, 'type' => 'same_device_match', 'status' => 'open',
        ]);
    }

    public function test_matchmaking_refuses_seating_flagged_pair(): void
    {
        $a = $this->fundedUser();
        $b = $this->fundedUser();

        $waiting = $this->matches->findOrCreateMatch($a, '1v1', 500);
        $this->assertSame('waiting', $waiting->status);

        // Suspected colluders: A shares an OPEN same-IP flag naming B.
        FraudFlag::create([
            'user_id' => $a->id,
            'type' => 'same_ip_match',
            'details' => ['match_id' => 4242, 'ip' => '9.9.9.9', 'other_user_ids' => [$b->id]],
            'status' => 'open',
            'created_at' => now(),
        ]);

        $other = $this->matches->findOrCreateMatch($b, '1v1', 500);

        $this->assertNotSame($waiting->id, $other->id, 'B must not be seated with flagged A');
        $this->assertSame('waiting', $other->status);
    }

    public function test_confirmed_fraud_freezes_wallet_and_debit_throws(): void
    {
        $admin = $this->admin();
        $user = $this->fundedUser();

        $flag = FraudFlag::create([
            'user_id' => $user->id,
            'type' => 'same_ip_match',
            'details' => ['ip' => '9.9.9.9', 'other_user_ids' => [999]],
            'status' => 'open',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('hmkr.fraud.confirm', $flag), [
            'action' => 'freeze_wallet',
        ])->assertRedirect();

        $this->assertSame('confirmed', $flag->fresh()->status);
        $this->assertSame('frozen', $user->fresh()->status);

        $this->expectException(WalletFrozenException::class);
        $this->wallets->debit($user->fresh(), 'bet', 500, 'frozen_test_'.uniqid());
    }

    public function test_fraud_dismiss_clears_flag(): void
    {
        $admin = $this->admin();
        $user = $this->fundedUser();

        $flag = FraudFlag::create([
            'user_id' => $user->id, 'type' => 'vpn_suspect',
            'details' => ['reasons' => ['xff_hops:4']], 'status' => 'open',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('hmkr.fraud.dismiss', $flag))->assertRedirect();
        $this->assertSame('dismissed', $flag->fresh()->status);
        $this->assertSame('active', $user->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 4. Bonus-abuse protection
    // ------------------------------------------------------------------

    public function test_referral_bonus_is_locked_until_wagered(): void
    {
        $user = $this->fundedUser(20000); // ₹200 deposit

        $entry = $this->wallets->credit($user, 'referral_bonus', 10000, 'rb_lock_'.uniqid());
        $lock = BonusLock::where('ledger_id', $entry->id)->first();
        $this->assertNotNull($lock);
        $this->assertSame(50000, $lock->required_wager_paise); // 10000 x 5
        $this->assertFalse((bool) $lock->released);

        // Ledger shows ₹300, but only ₹200 is spendable.
        $this->assertSame(30000, $this->wallets->balance($user));
        $this->assertSame(10000, $this->wallets->lockedBalance($user));
        $this->assertSame(20000, $this->wallets->availableBalance($user));

        // Trying to spend the locked bonus fails…
        try {
            $this->wallets->debit($user, 'bet', 25000, 'too_much_'.uniqid());
            $this->fail('Expected InsufficientBalanceException.');
        } catch (InsufficientBalanceException) {
            $this->assertTrue(true);
        }

        // …but spending within the available balance works and counts as wager.
        $this->wallets->debit($user, 'bet', 5000, 'ok_bet_'.uniqid());
        $this->assertSame(5000, $user->fresh()->wagered_paise);
        $this->assertFalse((bool) $lock->fresh()->released);
    }

    public function test_bonus_releases_after_enough_wagering(): void
    {
        $user = $this->fundedUser(60000);

        $this->wallets->credit($user, 'referral_bonus', 10000, 'rb_rel_'.uniqid());

        // Wager ₹500 x 100 = ₹50000 of bets (>= 5x the ₹100 bonus).
        for ($i = 0; $i < 100; $i++) {
            $this->wallets->debit($user, 'bet', 500, 'wager_'.$i.'_'.$user->id);
        }

        $this->assertSame(50000, $user->fresh()->wagered_paise);
        $this->assertSame(1, BonusLock::where('user_id', $user->id)->where('released', true)->count());
        $this->assertSame(0, $this->wallets->lockedBalance($user));
        // Now the full remaining ledger balance is spendable.
        $this->assertSame(
            $this->wallets->balance($user),
            $this->wallets->availableBalance($user)
        );
    }

    public function test_wallet_withdraw_returns_422_when_only_locked_bonus_remains(): void
    {
        $user = $this->fundedUser(0);
        $this->wallets->credit($user, 'referral_bonus', 10000, 'rb_wd_'.uniqid());

        // Ledger has ₹100 bonus but ₹0 is spendable: a ₹100 withdrawal
        // fails the available-balance check (422), not validation.
        $response = $this->actingAs($user)->postJson('/wallet/withdraw', [
            'amount' => 10000, 'method' => 'upi', 'upi_id' => 'test@upi',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_referral_bonus_credited_only_after_mobile_verified(): void
    {
        $referrer = $this->fundedUser(0);

        // Referee signs up WITH the referrer's code but no verified mobile.
        $referee = User::factory()->create([
            'referral_code' => $referrer->my_referral_code,
        ]);
        $this->assertNull($referee->mobile_verified_at);
        $this->assertSame(0, $this->wallets->balance($referrer), 'no bonus before verification');

        // Referee verifies mobile via the real OTP flow…
        $mobile = '9876543210';
        $this->actingAs($referee)->post('/mobile/otp/send', ['mobile' => $mobile]);
        $code = (new \App\Services\OtpService)->plainCodeForTesting($mobile);
        $this->actingAs($referee)->post('/mobile/otp/verify', [
            'mobile' => $mobile, 'code' => $code,
        ])->assertRedirect();

        // …and ONLY now the referrer gets the (locked) bonus.
        $this->assertSame(10000, $this->wallets->balance($referrer));
        $this->assertSame(1, BonusLock::where('user_id', $referrer->id)->count());
    }

    // ------------------------------------------------------------------
    // 5. VPN / proxy detection (heuristic, flag-only)
    // ------------------------------------------------------------------

    public function test_vpn_heuristic_flags_absurd_proxy_chain(): void
    {
        $check = app(HeuristicVpnCheck::class);

        $normal = \Illuminate\Http\Request::create('/play/find', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);
        $this->assertFalse($check->check($normal)->suspect);

        $proxied = \Illuminate\Http\Request::create('/play/find', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 198.51.100.7, 192.0.2.9, 10.1.2.3',
        ]);
        $result = $check->check($proxied);
        $this->assertTrue($result->suspect);
        $this->assertContains('xff_hops:4', $result->reasons);
    }

    public function test_vpn_suspicion_creates_flag_only_never_blocks(): void
    {
        $user = $this->fundedUser();

        // A "suspicious" request still plays fine — flag only.
        $response = $this->actingAs($user)
            ->withHeaders(['X-Forwarded-For' => '1.1.1.1, 2.2.2.2, 3.3.3.3, 4.4.4.4'])
            ->postJson('/play/find', ['mode' => '1v1', 'bet_paise' => 500]);

        $response->assertOk();
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user->id, 'type' => 'vpn_suspect', 'status' => 'open',
        ]);
        $this->assertSame('active', $user->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 6. Liquidity manager
    // ------------------------------------------------------------------

    public function test_table_ladder_follows_online_count(): void
    {
        $this->assertSame([500, 1000], TableManager::allowedBets(10));
        $this->assertSame([500, 1000], TableManager::allowedBets(150));
        $this->assertSame([500, 1000, 5000], TableManager::allowedBets(300));
        $this->assertSame([500, 1000, 5000, 10000, 50000], TableManager::allowedBets(1500));
    }

    public function test_tables_open_with_online_count(): void
    {
        // 0 online: only micro tables.
        $this->assertSame([500, 1000], TableManager::currentAllowedBets());

        // 250 online players: the ₹50 table opens.
        User::factory()->count(250)->create();
        User::query()->update(['last_seen_at' => now()]); // last_seen_at is not fillable
        $this->assertGreaterThanOrEqual(200, TableManager::onlineCount());
        $this->assertSame([500, 1000, 5000], TableManager::currentAllowedBets());

        // A funded player can now sit at the ₹50 table…
        $user = $this->fundedUser(100000);
        $match = $this->matches->findOrCreateMatch($user, '1v1', 5000);
        $this->assertSame(5000, $match->bet_paise);

        // …but not at ₹100 (still closed).
        try {
            $this->matches->findOrCreateMatch($user, '1v1', 10000);
            $this->fail('Expected TABLE_CLOSED.');
        } catch (LudoException $e) {
            $this->assertSame('TABLE_CLOSED', $e->errorCode);
        }
    }

    public function test_admin_can_override_tables_manually(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('hmkr.tables.update'), [
            'levels' => [500],
        ])->assertRedirect();

        $this->assertSame('0', Setting::get('tables_auto_mode'));
        $this->assertSame([500], TableManager::currentAllowedBets());

        $user = $this->fundedUser();
        try {
            $this->matches->findOrCreateMatch($user, '1v1', 1000);
            $this->fail('Expected TABLE_CLOSED.');
        } catch (LudoException $e) {
            $this->assertSame('TABLE_CLOSED', $e->errorCode);
        }
    }

    // ------------------------------------------------------------------
    // 7. Risk manager
    // ------------------------------------------------------------------

    public function test_risk_scan_raises_bot_difficulty_on_withdrawal_spike(): void
    {
        Setting::set('bot_difficulty', 'easy');

        $user = $this->fundedUser(0);
        $this->wallets->credit($user, 'deposit', 100000, 'risk_dep_'.uniqid());
        $this->wallets->debit($user, 'withdrawal', 95000, 'risk_wd_'.uniqid());

        Artisan::call('risk:scan');

        $this->assertSame('medium', Setting::get('bot_difficulty'));
        $this->assertDatabaseHas('admin_notices', ['type' => 'risk_withdrawal_spike']);
    }

    public function test_risk_scan_caps_bot_difficulty_at_hard(): void
    {
        Setting::set('bot_difficulty', 'hard');

        $user = $this->fundedUser(0);
        $this->wallets->credit($user, 'deposit', 100000, 'risk_dep2_'.uniqid());
        $this->wallets->debit($user, 'withdrawal', 95000, 'risk_wd2_'.uniqid());

        Artisan::call('risk:scan');

        $this->assertSame('hard', Setting::get('bot_difficulty'));
    }

    public function test_risk_scan_flags_high_winrate_players(): void
    {
        $shark = $this->fundedUser();
        $fish = $this->fundedUser();

        // 6 finished 1v1 matches, shark wins them all (100% > 70%).
        for ($i = 0; $i < 6; $i++) {
            $match = LudoMatch::create([
                'mode' => '1v1', 'bet_paise' => 500, 'status' => 'finished',
                'winner_user_id' => $shark->id, 'winning_team' => 0,
            ]);
            MatchPlayer::create(['match_id' => $match->id, 'user_id' => $shark->id, 'team' => 0, 'color' => 'red', 'score' => 100, 'status' => 'finished']);
            MatchPlayer::create(['match_id' => $match->id, 'user_id' => $fish->id, 'team' => 1, 'color' => 'green', 'score' => 10, 'status' => 'finished']);
        }

        Artisan::call('risk:scan');

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $shark->id, 'type' => 'high_winrate', 'status' => 'open',
        ]);
    }

    public function test_high_winrate_player_faces_hard_bots(): void
    {
        Setting::set('bot_fill_enabled', '1');
        Setting::set('bot_tables', '["500"]');
        Setting::set('bot_max_per_match', '1');
        Setting::set('bot_join_after_seconds', '0');
        Setting::set('bot_difficulty', 'easy');

        $shark = $this->fundedUser();
        FraudFlag::create([
            'user_id' => $shark->id, 'type' => 'high_winrate',
            'details' => ['win_rate' => 1.0, 'games' => 6], 'status' => 'open',
            'created_at' => now(),
        ]);

        $bot = User::factory()->create(['username' => 'riskbot1', 'role' => 'bot', 'status' => 'active']);

        $match = $this->matches->findOrCreateMatch($shark, '1v1', 500);
        $match->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $this->matches->tick();

        $botSeat = $match->fresh()->players()->where('is_bot', true)->first();
        $this->assertNotNull($botSeat);
        $this->assertSame('hard', $botSeat->bot_difficulty, 'flagged shark faces hard bots');
    }

    // ------------------------------------------------------------------
    // 8. Maintenance mode
    // ------------------------------------------------------------------

    public function test_maintenance_mode_serves_503_public_but_not_admin(): void
    {
        Setting::set('maintenance_mode', '1');

        $this->get('/')->assertStatus(503);
        $this->get('/about')->assertStatus(503);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/hmkr')->assertOk();
        $this->actingAs($admin)->get('/hmkr/users')->assertOk();
    }

    public function test_maintenance_toggle_from_dashboard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('hmkr.maintenance'), ['enabled' => '1']);
        $this->assertSame('1', Setting::get('maintenance_mode'));

        $this->actingAs($admin)->post(route('hmkr.maintenance'), ['enabled' => '0']);
        $this->assertSame('0', Setting::get('maintenance_mode'));
        $this->get('/')->assertOk();
    }

    // ------------------------------------------------------------------
    // 9. Admin panel
    // ------------------------------------------------------------------

    public function test_admin_dashboard_renders(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/hmkr')
            ->assertOk()
            ->assertSee('Platform Health')
            ->assertSee('Open fraud alerts');
    }

    public function test_non_admin_cannot_reach_admin(): void
    {
        $user = $this->fundedUser();

        $this->actingAs($user)->get('/hmkr')->assertForbidden();
    }

    public function test_admin_user_suspend_and_freeze_flow(): void
    {
        $admin = $this->admin();
        $user = $this->fundedUser();

        $this->actingAs($admin)->get(route('hmkr.users.show', $user))->assertOk()
            ->assertSee('Spendable (available)');

        $this->actingAs($admin)->post(route('hmkr.users.suspend', $user))->assertRedirect();
        $this->assertSame('suspended', $user->fresh()->status);

        $this->actingAs($admin)->post(route('hmkr.users.unsuspend', $user))->assertRedirect();
        $this->assertSame('active', $user->fresh()->status);

        $this->actingAs($admin)->post(route('hmkr.users.freeze', $user))->assertRedirect();
        $this->assertSame('frozen', $user->fresh()->status);

        $this->actingAs($admin)->post(route('hmkr.users.unfreeze', $user))->assertRedirect();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_admin_devices_ban_unban(): void
    {
        $admin = $this->admin();
        $hash = $this->deviceHash('admin-ban');

        $this->actingAs($admin)->post(route('hmkr.devices.ban'), ['device_hash' => $hash])
            ->assertRedirect();
        $this->assertDatabaseHas('banned_devices', ['device_hash' => $hash]);

        $banned = BannedDevice::where('device_hash', $hash)->first();
        $this->actingAs($admin)->delete(route('hmkr.devices.unban', $banned))->assertRedirect();
        $this->assertDatabaseMissing('banned_devices', ['device_hash' => $hash]);
    }

    // ------------------------------------------------------------------
    // 10. API platform
    // ------------------------------------------------------------------

    public function test_public_api_key_auth(): void
    {
        [$key, $plain] = ApiKey::generate('Test Public', 'public');

        $user = $this->fundedUser(0);
        $gameId = $user->fresh()->game_id;
        $this->assertNotEmpty($gameId);

        // No key -> 401.
        $this->postJson('/api/v1/public/player/login', ['game_id' => $gameId])
            ->assertStatus(401);

        // Wrong key -> 401.
        $this->withHeaders(['Authorization' => 'Bearer lv_pub_wrongkey'])
            ->postJson('/api/v1/public/player/login', ['game_id' => $gameId])
            ->assertStatus(401);

        // Right key -> player token.
        $response = $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/public/player/login', ['game_id' => $gameId])
            ->assertOk();
        $playerToken = $response->json('player_token');
        $this->assertNotEmpty($playerToken);

        // Player token opens the game endpoints.
        $this->withHeaders(['Authorization' => "Bearer {$playerToken}"])
            ->getJson('/api/v1/public/wallet/balance')
            ->assertOk()
            ->assertJson(['balance_paise' => 0, 'available_paise' => 0]);

        // Disabled key -> 403.
        $key->forceFill(['is_active' => false])->save();
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/public/player/login', ['game_id' => $gameId])
            ->assertStatus(403);
    }

    public function test_public_api_match_flow(): void
    {
        [$key, $plain] = ApiKey::generate('Test Public 2', 'public');
        $user = $this->fundedUser(100000);

        $login = $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/public/player/login', [
                'email' => $user->email, 'password' => 'password',
            ])->assertOk();
        $playerToken = $login->json('player_token');
        $auth = ['Authorization' => "Bearer {$playerToken}"];

        $start = $this->withHeaders($auth)->postJson('/api/v1/public/match/start', [
            'mode' => '1v1', 'bet' => 500,
        ])->assertCreated();
        $matchId = $start->json('match_id');

        $this->withHeaders($auth)->getJson("/api/v1/public/match/{$matchId}/result")
            ->assertOk()
            ->assertJson(['id' => $matchId, 'status' => 'waiting']);

        // Another player's token cannot see this match.
        $other = $this->fundedUser(0);
        $otherGameId = $other->fresh()->game_id;
        $otherLogin = $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/public/player/login', ['game_id' => $otherGameId])->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$otherLogin->json('player_token')])
            ->getJson("/api/v1/public/match/{$matchId}/result")
            ->assertStatus(404);
    }

    public function test_partner_api_ip_whitelist_enforced(): void
    {
        [$key, $plain] = ApiKey::generate('Test Partner', 'private', ['10.0.0.1']);
        $user = $this->fundedUser(50000);

        // Test requests come from 127.0.0.1 — not whitelisted -> 403.
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/partner/wallet/credit', [
                'user_id' => $user->id,
                'amount_paise' => 1000,
                'reference_id' => 'partner_test_1',
            ])->assertStatus(403);

        // Public keys cannot touch partner endpoints either.
        [$pubKey, $pubPlain] = ApiKey::generate('Test Public 3', 'public');
        $this->withHeaders(['Authorization' => "Bearer {$pubPlain}"])
            ->postJson('/api/v1/partner/wallet/credit', [
                'user_id' => $user->id,
                'amount_paise' => 1000,
                'reference_id' => 'partner_test_2',
            ])->assertStatus(403);

        // Whitelisted private key works, with idempotency on reference_id.
        $key->forceFill(['ip_whitelist' => ['127.0.0.1']])->save();
        $payload = [
            'user_id' => $user->id,
            'amount_paise' => 1000,
            'reference_id' => 'partner_test_3',
        ];
        $first = $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/partner/wallet/credit', $payload)->assertOk();
        $second = $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/partner/wallet/credit', $payload)->assertOk();

        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($first->json('entry_id'), $second->json('entry_id'));
        // …and the money moved exactly once.
        $this->assertSame(51000, $this->wallets->balance($user));

        // Debit is blocked on frozen wallets.
        $user->forceFill(['status' => 'frozen'])->save();
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->postJson('/api/v1/partner/wallet/debit', [
                'user_id' => $user->id,
                'amount_paise' => 500,
                'reference_id' => 'partner_test_4',
            ])->assertStatus(422);
    }

    public function test_api_key_generate_disable_regenerate_and_logs(): void
    {
        $admin = $this->admin();

        // Generate (plain key shown once via session).
        $response = $this->actingAs($admin)->post(route('hmkr.api-keys.store'), [
            'name' => 'Docs Partner', 'type' => 'private', 'ip_whitelist' => '127.0.0.1',
        ])->assertRedirect();
        $plain = $response->getSession()->get('plain_key');
        $this->assertNotEmpty($plain);
        $this->assertStringStartsWith('lv_priv_', $plain);
        $key = ApiKey::where('name', 'Docs Partner')->first();
        $this->assertNotNull($key);
        $this->assertSame(['127.0.0.1'], $key->ip_whitelist);

        // Use it once so a log row exists…
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/partner/tournaments')->assertOk();
        $this->assertSame(1, ApiKeyLog::where('key_id', $key->id)->count());

        // …admin can view the logs…
        $this->actingAs($admin)->get(route('hmkr.api-keys.logs', $key))
            ->assertOk()->assertSee('api/v1/partner/tournaments');

        // …disable → 403…
        $this->actingAs($admin)->post(route('hmkr.api-keys.disable', $key))->assertRedirect();
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/partner/tournaments')->assertStatus(403);

        // …regenerate → new plain key, old one dead…
        $response = $this->actingAs($admin)->post(route('hmkr.api-keys.regenerate', $key))->assertRedirect();
        $newPlain = $response->getSession()->get('plain_key');
        $this->assertNotSame($plain, $newPlain);
        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/partner/tournaments')->assertStatus(401);

        // …delete removes it entirely.
        $this->actingAs($admin)->delete(route('hmkr.api-keys.destroy', $key))->assertRedirect();
        $this->assertDatabaseMissing('api_keys', ['id' => $key->id]);
    }

    // ------------------------------------------------------------------
    // 11. API docs
    // ------------------------------------------------------------------

    public function test_api_docs_pages_render(): void
    {
        $this->get('/api/docs')->assertOk()
            ->assertSee('/api/v1/public/player/login')
            ->assertSee('Authorization: Bearer');
        $this->get('/api/docs/embedding')->assertOk()
            ->assertSee('iframe');
    }

    // ------------------------------------------------------------------
    // 12. Support + chatbot
    // ------------------------------------------------------------------

    public function test_chatbot_auto_replies_on_keyword(): void
    {
        $user = $this->fundedUser();

        $response = $this->actingAs($user)->post(route('support.store'), [
            'subject' => 'Where is my withdrawal?',
            'message' => 'I requested a withdrawal yesterday and it has not arrived.',
        ])->assertRedirect();

        $ticket = SupportTicket::where('user_id', $user->id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame('open', $ticket->status, 'bot never closes the ticket');

        $reply = $ticket->replies()->where('is_bot', true)->first();
        $this->assertNotNull($reply, 'keyword "withdraw" should trigger an auto-reply');
        $this->assertStringContainsString('Automated reply', $reply->body);

        // A ticket with no keyword gets no auto-reply.
        $this->actingAs($user)->post(route('support.store'), [
            'subject' => 'Just saying hello',
            'message' => 'Nothing to report, all good.',
        ]);
        $plain = SupportTicket::where('user_id', $user->id)->orderByDesc('id')->first();
        $this->assertSame(0, $plain->replies()->where('is_bot', true)->count());
    }

    public function test_support_user_flow_and_admin_reply_close(): void
    {
        $admin = $this->admin();
        $user = $this->fundedUser();

        $this->actingAs($user)->get(route('support.index'))->assertOk();
        $this->actingAs($user)->get(route('support.create'))->assertOk();
        $this->actingAs($user)->post(route('support.store'), [
            'subject' => 'Need help', 'message' => 'Please assist.',
        ])->assertRedirect();
        $ticket = SupportTicket::where('user_id', $user->id)->first();
        $this->actingAs($user)->get(route('support.show', $ticket))->assertOk();

        // Admin replies and closes.
        $this->actingAs($admin)->post(route('hmkr.support.reply', $ticket), [
            'body' => 'We are looking into it.',
        ])->assertRedirect();
        $this->assertSame('answered', $ticket->fresh()->status);

        $this->actingAs($admin)->post(route('hmkr.support.close', $ticket))->assertRedirect();
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 13. Iframe embed tokens
    // ------------------------------------------------------------------

    public function test_embed_token_issue_and_verify(): void
    {
        [$key, $plain] = ApiKey::generate('Embed Partner', 'public');
        $svc = app(EmbedTokenService::class);

        $token = $svc->issue($key, 3600);
        $verified = $svc->verify($token);
        $this->assertNotNull($verified);
        $this->assertSame($key->id, $verified->id);

        // Tampered signature fails.
        $parts = explode('.', $token);
        $this->assertNull($svc->verify($parts[0].'.'.str_repeat('A', strlen($parts[1]))));

        // Garbage fails.
        $this->assertNull($svc->verify('not-a-token'));

        // Expired fails.
        $expired = $svc->issue($key, 60);
        $this->travel(2)->hours();
        $this->assertNull($svc->verify($expired));
        $this->travelBack();
    }

    public function test_embed_board_requires_valid_token(): void
    {
        [$key, $plain] = ApiKey::generate('Embed Partner 2', 'public');
        $a = $this->fundedUser();
        $b = $this->fundedUser();
        $match = $this->matches->findOrCreateMatch($a, '1v1', 500);
        $match = $this->matches->findOrCreateMatch($b, '1v1', 500);

        $token = app(EmbedTokenService::class)->issue($key, 3600);

        $this->get("/embed/match/{$match->id}?token={$token}")
            ->assertOk()
            ->assertSee('Match #'.$match->id);

        $this->get("/embed/match/{$match->id}?token=bogus")
            ->assertStatus(403);
        $this->get("/embed/match/{$match->id}")
            ->assertStatus(403);
    }

    public function test_player_token_expires(): void
    {
        $user = $this->fundedUser(0);
        [$token, $plain] = PlayerToken::issue($user);

        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/public/wallet/balance')->assertOk();

        $this->travel(2)->days();

        $this->withHeaders(['Authorization' => "Bearer {$plain}"])
            ->getJson('/api/v1/public/wallet/balance')->assertStatus(401);

        $this->travelBack();
    }
}
