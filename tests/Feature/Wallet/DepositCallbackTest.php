<?php

namespace Tests\Feature\Wallet;

use App\Models\Deposit;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Tests\Feature\LicensedTestCase;

/**
 * The gateway callback must verify the signature and credit the ledger
 * exactly once per payment — duplicate callbacks are acknowledged without
 * a second ledger entry.
 */
class DepositCallbackTest extends LicensedTestCase
{
    protected string $secret = 'test_key_secret_abc123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.razorpay.key_secret' => $this->secret]);
    }

    protected function payload(string $orderId, string $paymentId): array
    {
        return [
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret),
        ];
    }

    public function test_valid_callback_credits_ledger_once(): void
    {
        $user = User::factory()->create();
        Deposit::create([
            'user_id' => $user->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_cb_1',
            'amount_paise' => 25000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson(
            '/wallet/deposit/callback',
            $this->payload('order_cb_1', 'pay_cb_1')
        );

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(25000, app(WalletService::class)->balance($user));
        $this->assertSame('completed', Deposit::where('gateway_order_id', 'order_cb_1')->value('status'));
        $this->assertSame(
            1,
            WalletLedger::where('reference_id', 'rzp_pay_cb_1')->count(),
            'Exactly one ledger entry per payment.'
        );
    }

    public function test_duplicate_callback_creates_no_second_entry(): void
    {
        $user = User::factory()->create();
        Deposit::create([
            'user_id' => $user->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_cb_2',
            'amount_paise' => 10000,
            'status' => 'pending',
        ]);

        $payload = $this->payload('order_cb_2', 'pay_cb_2');

        $this->actingAs($user)->postJson('/wallet/deposit/callback', $payload)->assertOk();
        // Exact same callback again (retried webhook / double handler call).
        $second = $this->actingAs($user)->postJson('/wallet/deposit/callback', $payload);
        $second->assertOk()->assertJson(['ok' => true, 'duplicate' => true]);

        $this->assertSame(10000, app(WalletService::class)->balance($user));
        $this->assertSame(1, WalletLedger::where('reference_id', 'rzp_pay_cb_2')->count());
    }

    public function test_invalid_signature_never_credits(): void
    {
        $user = User::factory()->create();
        Deposit::create([
            'user_id' => $user->id,
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_cb_3',
            'amount_paise' => 50000,
            'status' => 'pending',
        ]);

        $payload = $this->payload('order_cb_3', 'pay_cb_3');
        $payload['razorpay_signature'] = 'bad'.substr($payload['razorpay_signature'], 3);

        $this->actingAs($user)->postJson('/wallet/deposit/callback', $payload)->assertStatus(422);

        $this->assertSame(0, app(WalletService::class)->balance($user));
        $this->assertSame('failed', Deposit::where('gateway_order_id', 'order_cb_3')->value('status'));
        $this->assertSame(0, WalletLedger::where('user_id', $user->id)->count());
    }

    public function test_callback_for_unknown_order_is_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(
            '/wallet/deposit/callback',
            $this->payload('order_nope', 'pay_nope')
        )->assertNotFound();
    }
}
