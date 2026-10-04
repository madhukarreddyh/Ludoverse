<?php

namespace Tests\Feature\Wallet;

use App\Services\Payments\GatewayNotAvailableException;
use App\Services\Payments\PaytmGateway;
use App\Services\Payments\RazorpayGateway;
use Tests\Feature\LicensedTestCase;

/**
 * Razorpay callback signature verification, exactly per Razorpay docs:
 *   HMAC-SHA256("razorpay_order_id|razorpay_payment_id", key_secret)
 * compared with hash_equals() against razorpay_signature.
 */
class RazorpayGatewayTest extends LicensedTestCase
{
    protected string $secret = 'test_key_secret_abc123';

    protected RazorpayGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.razorpay.key_secret' => $this->secret]);
        $this->gateway = new RazorpayGateway();
    }

    public function test_valid_signature_passes_with_known_test_vector(): void
    {
        $orderId = 'order_9A33XWu170gUtm';
        $paymentId = 'pay_9A33YEGYJGlVHM';

        // Known-good vector computed per the documented formula.
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret);

        $this->assertTrue($this->gateway->verifyCallback([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
        ], $signature));
    }

    public function test_tampered_signature_fails(): void
    {
        $orderId = 'order_9A33XWu170gUtm';
        $paymentId = 'pay_9A33YEGYJGlVHM';
        $good = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret);

        // Flip the last hex char of the signature.
        $tampered = substr($good, 0, -1).(substr($good, -1) === '0' ? '1' : '0');

        $this->assertFalse($this->gateway->verifyCallback([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
        ], $tampered));
    }

    public function test_tampered_payment_id_fails(): void
    {
        $orderId = 'order_9A33XWu170gUtm';
        $signature = hash_hmac('sha256', $orderId.'|pay_9A33YEGYJGlVHM', $this->secret);

        // Attacker swaps in a different payment id while keeping the signature.
        $this->assertFalse($this->gateway->verifyCallback([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => 'pay_EVIL9999999999',
        ], $signature));
    }

    public function test_wrong_secret_fails(): void
    {
        $orderId = 'order_1';
        $paymentId = 'pay_1';
        $signature = hash_hmac('sha256', $orderId.'|'.$paymentId, 'some_other_secret');

        $this->assertFalse($this->gateway->verifyCallback([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
        ], $signature));
    }

    public function test_missing_fields_fail_closed(): void
    {
        $this->assertFalse($this->gateway->verifyCallback([], 'anything'));
        $this->assertFalse($this->gateway->verifyCallback(['razorpay_order_id' => 'x'], 'anything'));
        $this->assertFalse($this->gateway->verifyCallback(['razorpay_payment_id' => 'y'], 'anything'));
    }

    public function test_paytm_is_a_disabled_stub(): void
    {
        $paytm = new PaytmGateway();

        $this->assertSame('paytm', $paytm->name());
        $this->assertFalse($paytm->isEnabled());

        $this->expectException(GatewayNotAvailableException::class);
        $paytm->createOrder(1000);
    }
}
