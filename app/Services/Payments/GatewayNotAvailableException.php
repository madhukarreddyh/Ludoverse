<?php

namespace App\Services\Payments;

/**
 * Thrown when a gateway is called while disabled or unconfigured.
 */
class GatewayNotAvailableException extends \RuntimeException
{
}
