<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Result of a VPN/proxy heuristic check: a suspicion, never a verdict.
 */
class VpnCheckResult
{
    /** @param string[] $reasons */
    public function __construct(
        public readonly bool $suspect,
        public readonly array $reasons = [],
    ) {}
}

/**
 * Contract for VPN/proxy detection. Implementations return a suspicion
 * flag — callers must NEVER auto-block on it.
 */
interface VpnDetectionService
{
    public function check(Request $request): VpnCheckResult;
}
