<?php

namespace App\Services\Payments;

/**
 * Contract every payment gateway must implement.
 * All money amounts are in paise (integers).
 */
interface PaymentGateway
{
    /**
     * Machine name of the gateway (e.g. 'razorpay', 'paytm').
     */
    public function name(): string;

    /**
     * Create a gateway order and return at least:
     *   ['gateway_order_id' => string, ...gateway-specific fields...]
     *
     * @throws GatewayNotAvailableException when the gateway is disabled.
     */
    public function createOrder(int $amountPaise, array $meta = []): array;

    /**
     * Verify a payment callback payload against its signature.
     * MUST be implemented exactly per the gateway's docs — a skipped or
     * weak check here lets attackers credit their wallet for free.
     */
    public function verifyCallback(array $payload, string $signature): bool;

    /**
     * Whether the gateway may be used right now (admin toggle in settings).
     */
    public function isEnabled(): bool;
}
