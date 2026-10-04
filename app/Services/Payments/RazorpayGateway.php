<?php

namespace App\Services\Payments;

use App\Models\Setting;
use Razorpay\Api\Api;

/**
 * Razorpay integration (live SDK for order creation; manual signature
 * verification implemented exactly per Razorpay docs).
 */
class RazorpayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function isEnabled(): bool
    {
        return Setting::bool('gateway_razorpay_enabled')
            && config('services.razorpay.key_id') !== ''
            && config('services.razorpay.key_secret') !== '';
    }

    /**
     * Create a Razorpay order (amount in paise, INR).
     *
     * @throws GatewayNotAvailableException when disabled/unconfigured.
     */
    public function createOrder(int $amountPaise, array $meta = []): array
    {
        if (! $this->isEnabled()) {
            throw new GatewayNotAvailableException('Razorpay is not enabled or keys are missing.');
        }

        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException('Order amount must be positive.');
        }

        $api = new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));

        $receipt = $meta['receipt']
            ?? ('deposit_'.now()->format('YmdHis').'_'.($meta['user_id'] ?? 'guest'));

        $order = $api->order->create([
            'amount' => $amountPaise,          // Razorpay also takes paise.
            'currency' => 'INR',
            'receipt' => $receipt,
            'payment_capture' => 1,            // auto-capture on payment success.
        ]);

        return [
            'gateway_order_id' => $order['id'],
            'key_id' => config('services.razorpay.key_id'),
            'amount' => $order['amount'],
            'currency' => $order['currency'],
        ];
    }

    /**
     * Verify a checkout/webhook callback exactly per Razorpay docs:
     *
     *   expected = HMAC-SHA256("razorpay_order_id|razorpay_payment_id", key_secret)
     *   valid    = hash_equals(expected, razorpay_signature)
     *
     * Missing fields => false (never throw on attacker input; the caller
     * turns a false into a failed deposit, never a credit).
     */
    public function verifyCallback(array $payload, string $signature): bool
    {
        $orderId = $payload['razorpay_order_id'] ?? null;
        $paymentId = $payload['razorpay_payment_id'] ?? null;

        if (! is_string($orderId) || $orderId === '' || ! is_string($paymentId) || $paymentId === '') {
            return false;
        }

        // Constant-time compare: no early-exit timing oracle on the signature.
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }
}
