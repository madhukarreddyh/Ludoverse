<?php

namespace Tests\Feature\Wallet;

use App\Models\ManualDeposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Tests\Feature\LicensedTestCase;

/**
 * Admin-only routes must 403 for non-admin users; guests bounce to login.
 * Covers all new /hmkr wallet-admin routes.
 */
class WalletAdminAccessTest extends LicensedTestCase
{
    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function regularUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_non_admin_gets_403_on_all_wallet_admin_routes(): void
    {
        $user = $this->regularUser();
        $acting = fn () => $this->actingAs($user);

        $deposit = ManualDeposit::create([
            'user_id' => $user->id, 'amount_paise' => 1000,
            'utr' => 'UTR403TEST', 'screenshot_path' => 'deposits/x.jpg', 'status' => 'pending',
        ]);
        $withdrawal = Withdrawal::create([
            'user_id' => $user->id, 'amount_paise' => 10000, 'method' => 'upi',
            'details' => ['upi_id' => 'x@upi'],
            'tds_paise' => 3000, 'commission_paise' => 200, 'net_paise' => 6800,
            'status' => 'pending',
        ]);

        $acting()->get('/hmkr/deposits')->assertForbidden();
        $acting()->get('/hmkr/withdrawals')->assertForbidden();
        $acting()->post("/hmkr/deposits/{$deposit->id}/approve")->assertForbidden();
        $acting()->post("/hmkr/deposits/{$deposit->id}/reject")->assertForbidden();
        $acting()->post("/hmkr/withdrawals/{$withdrawal->id}/approve")->assertForbidden();
        $acting()->post("/hmkr/withdrawals/{$withdrawal->id}/paid")->assertForbidden();
        $acting()->post("/hmkr/withdrawals/{$withdrawal->id}/reject")->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/hmkr/deposits')->assertRedirect('/login');
        $this->get('/hmkr/withdrawals')->assertRedirect('/login');
        $this->get('/wallet')->assertRedirect('/login');
        $this->get('/wallet/deposit')->assertRedirect('/login');
        $this->get('/wallet/withdraw')->assertRedirect('/login');
    }

    public function test_admin_can_open_deposits_and_withdrawals_pages(): void
    {
        $this->actingAs($this->admin())->get('/hmkr/deposits')->assertOk();
        $this->actingAs($this->admin())->get('/hmkr/withdrawals')->assertOk();
    }

    public function test_wallet_pages_render_for_users(): void
    {
        $user = $this->regularUser();
        app(WalletService::class)->credit($user, 'deposit', 12345, 'page_seed_1');

        $this->actingAs($user)->get('/wallet')->assertOk()->assertSee('123.45');
        $this->actingAs($user)->get('/wallet/deposit')->assertOk();
        $this->actingAs($user)->get('/wallet/withdraw')->assertOk();
    }

    public function test_admin_cannot_act_on_already_reviewed_withdrawal(): void
    {
        $admin = $this->admin();
        $w = Withdrawal::create([
            'user_id' => $this->regularUser()->id,
            'amount_paise' => 10000,
            'method' => 'upi',
            'details' => ['upi_id' => 'x@upi'],
            'tds_paise' => 3000,
            'commission_paise' => 200,
            'net_paise' => 6800,
            'status' => 'rejected', // already decided
        ]);

        $this->actingAs($admin)->post("/hmkr/withdrawals/{$w->id}/approve")
            ->assertSessionHasErrors('status');
    }

    public function test_reconcile_command_passes_on_clean_data(): void
    {
        $user = $this->regularUser();
        app(WalletService::class)->credit($user, 'deposit', 5000, 'rec_seed_1');
        app(WalletService::class)->debit($user, 'bet', 1500, 'rec_seed_2');

        $this->artisan('wallet:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('no mismatches');
    }

    public function test_reconcile_command_detects_cached_column_drift(): void
    {
        $user = $this->regularUser();
        app(WalletService::class)->credit($user, 'deposit', 5000, 'rec_seed_3');

        // Corrupt the cache directly, bypassing the service.
        $user->update(['wallet_balance_paise' => 999999]);

        $this->artisan('wallet:reconcile')
            ->assertFailed()
            ->expectsOutputToContain('MISMATCH');
    }
}
