<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Heuristic VPN/proxy check.
 *
 * HONEST LIMITS — read before trusting this:
 *  - There is NO reliable way to detect a VPN from request headers.
 *    Real detection needs a commercial IP-intelligence provider
 *    (IPQualityScore, MaxMind minFraud, etc.) queried per client IP.
 *  - These heuristics only catch MISCONFIGURED or NAIVE setups: proxy
 *    headers left in place, absurd X-Forwarded-For chains, private IPs
 *    claiming to be remote clients.
 *  - A competent VPN user passes every check below. Corporate NATs,
 *    mobile carrier-grade NAT and Cloudflare-style setups can TRIP them.
 *
 * Therefore the result is FLAG-ONLY (fraud_flags type 'vpn_suspect'):
 * it feeds analyst review, never an automatic block or ban.
 */
class HeuristicVpnCheck implements VpnDetectionService
{
    /**
     * Headers that honest clients never send but proxies/VPN exit
     * tooling sometimes leaves behind.
     */
    protected const SUSPICIOUS_HEADERS = [
        'X-VPN', 'X-Proxy-ID', 'X-Tor', 'X-Forwarded-For-Original',
    ];

    public function check(Request $request): VpnCheckResult
    {
        $reasons = [];

        // 1. Absurd proxy chains: more than 2 X-Forwarded-For hops means
        //    at least two intermediaries — unusual for a real player.
        $xff = $request->header('X-Forwarded-For', '');
        $hops = array_filter(array_map('trim', explode(',', (string) $xff)));
        if (count($hops) > 2) {
            $reasons[] = 'xff_hops:'.count($hops);
        }

        // 2. Leftover VPN/proxy-identifying headers.
        foreach (self::SUSPICIOUS_HEADERS as $header) {
            if ($request->headers->has($header)) {
                $reasons[] = 'header:'.strtolower($header);
            }
        }
        $via = strtolower((string) $request->header('Via', ''));
        if ($via !== '' && preg_match('/proxy|vpn|squid/', $via)) {
            $reasons[] = 'via_proxy';
        }

        // 3. The claimed client IP is private/reserved space — a remote
        //    player can never legitimately BE 10.x or 192.168.x.
        $clientIp = $request->ip();
        if ($clientIp && ! filter_var(
            $clientIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            $reasons[] = 'private_client_ip:'.$clientIp;
        }

        return new VpnCheckResult($reasons !== [], $reasons);
    }
}
