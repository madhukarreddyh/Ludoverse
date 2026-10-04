<?php

namespace Tests\Feature\Wallet;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletLedger;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalBreakdown;
use Tests\Feature\LicensedTestCase;

/**
 * Withdrawal breakdown math + the hold/approve/pay/reject state machine.
 */
class WithdrawalTest extends LicensedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('withdrawal_commission_rate', '2');
        Setting::set('tds_rate', '30');
    }

    public function test_breakdown_math_is_exact_in_paise(): void
    {
        // ₹1,000 = 100000 paise: 2% commission = 2000, 30% TDS = 30000.
        $b = WithdrawalBreakdown::for(100000);

        $this->assertSame(100000, $b['amount']);
        $this->assertSame(2000, $b['commission']);
        $this->assertSame(30000, $b['tds']);
        $this->assertSame(68000, $b['net']);
    }

    public function test_breakdown_rounds_to_whole_paise(): void
    {
        // 999 paise: 2% = 19.98 -> 20, 30% = 299.7 -> 300, net = 679.
        $b = WithdrawalBreakdown::for(999);

        $this->assertSame(20, $b['commission']);
        $this->assertSame(300, $b['tds']);
        $this->assertSame(679, $b['net']);
    }

    public function test_breakdown_preview_endpoint_matches_server_math(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/wallet/withdraw/preview', ['amount' => 100000]);

        $response->assertOk()->assertJson([
            'amount' => 100000,
            'commission' => 2000,
            'tds' => 30000,
            'net' => 68000,
        ]);
    }

    public function test_withdrawal_request_debits_immediately_and_holds(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 'deposit', 100000, 'wdr_seed_1');

        $response = $this->actingAs($user)->post('/wallet/withdraw', [
            'amount' => 50000,
            'method' => 'upi',
            'upi_id' => 'user@upi',
        ]);

        $response->assertRedirect(route('wallet.index'));

        // Full amount held at once (debit), breakdown stored on the row.
        $this->assertSame(50000, app(WalletService::class)->balance($user));
        $w = Withdrawal::where('user_id', $user->id)->first();
        $this->assertSame('pending', $w->status);
        $this->assertSame(50000, $w->amount_paise);
        $this->assertSame(15000, $w->tds_paise);
        $this->assertSame(1000, $w->commission_paise);
        $this->assertSame(34000, $w->net_paise);
        $this->assertSame(['upi_id' => 'user@upi'], $w->details);
    }

    public function test_insufficient_balance_returns_422(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 'deposit', 5000, 'wdr_seed_2');

        $this->actingAs($user)->postJson('/wallet/withdraw', [
            'amount' => 10000, // min ₹100, exceeds the ₹50 balance
            'method' => 'upi',
            'upi_id' => 'user@upi',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame(5000, app(WalletService::class)->balance($user));
        $this->assertSame(0, Withdrawal::where('user_id', $user->id)->count());
    }

    public function test_admin_approve_then_paid_lifecycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 'deposit', 100000, 'wdr_seed_3');

        $this->actingAs($user)->post('/wallet/withdraw', [
            'amount' => 40000, 'method' => 'bank',
            'account_no' => '1234567890', 'ifsc' => 'HDFC0001234', 'account_name' => 'Test User',
        ]);

        $w = Withdrawal::where('user_id', $user->id)->first();

        $this->actingAs($admin)->post("/hmkr/withdrawals/{$w->id}/approve")
            ->assertRedirect();
        $this->assertSame('approved', $w->fresh()->status);

        $this->actingAs($admin)->post("/hmkr/withdrawals/{$w->id}/paid")
            ->assertRedirect();
        $this->assertSame('paid', $w->fresh()->status);

        // Funds stay held — balance unchanged since the request debit.
        $this->assertSame(60000, app(WalletService::class)->balance($user));
    }

    public function test_admin_reject_returns_held_funds_via_compensating_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 'deposit', 100000, 'wdr_seed_4');

        $this->actingAs($user)->post('/wallet/withdraw', [
            'amount' => 40000, 'method' => 'upi', 'upi_id' => 'user@upi',
        ]);
        $this->assertSame(60000, app(WalletService::class)->balance($user));

        $w = Withdrawal::where('user_id', $user->id)->first();

        $this->actingAs($admin)->post("/hmkr/withdrawals/{$w->id}/reject")
            ->assertRedirect();

        $this->assertSame('rejected', $w->fresh()->status);
        // Compensating entry: type 'withdrawal', POSITIVE amount, back to 100000.
        $this->assertSame(100000, app(WalletService::class)->balance($user));
        $refund = WalletLedger::where('reference_id', 'wdr_refund_'.$w->id)->first();
        $this->assertNotNull($refund);
        $this->assertSame('withdrawal', $refund->transaction_type);
        $this->assertSame(40000, $refund->amount_paise);
        $this->assertSame('wdr_hold', $refund->meta['reversal_of']);
    }
}
