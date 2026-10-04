<?php

namespace App\Services\Payments;

use App\Models\Setting;

/**
 * Paytm integration — STUB ONLY.
 *
 * Paytm's gateway API/availability for this platform is not confirmed yet,
 * so this class exists only to satisfy the PaymentGateway contract and make
 * the "not wired up" state explicit and loud: every operation fails fast
 * with GatewayNotAvailableException instead of silently doing nothing.
 */
class PaytmGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paytm';
    }

    public function isEnabled(): bool
    {
        // Hard off: Paytm is not integrated in this phase.
        return false;
    }

    public function createOrder(int $amountPaise, array $meta = []): array
    {
        throw new GatewayNotAvailableException(
            'Paytm is not integrated yet. It is a stub in this phase; deposits must use Razorpay or manual UPI.'
        );
    }

    public function verifyCallback(array $payload, string $signature): bool
    {
        throw new GatewayNotAvailableException(
            'Paytm is not integrated yet. No Paytm callbacks can be verified.'
        );
    }
}
