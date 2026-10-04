<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Liquidity manager: which bet levels are open depends on how many
 * players are online. Thin liquidity + big tables = long waits and
 * bot-filled games; the ladder opens higher stakes only when enough
 * real players are around to fill them.
 *
 * Admin override: tables_auto_mode = 0 switches to the manual list in
 * tables_manual_bets (JSON array of paise).
 */
class TableManager
{
    /**
     * Bet levels (paise) allowed for a given online-player count.
     */
    public static function allowedBets(int $onlineCount): array
    {
        if ($onlineCount > 1000) {
            return [500, 1000, 5000, 10000, 50000];
        }
        if ($onlineCount > 200) {
            return [500, 1000, 5000];
        }

        // < 50 and 50–200 both stay on the micro tables: opening ₹50+
        // tables with thin liquidity just strands players in lobbies.
        return [500, 1000];
    }

    /**
     * Online = last_seen_at within 5 minutes.
     */
    public static function onlineCount(): int
    {
        return User::where('last_seen_at', '>=', now()->subMinutes(5))->count();
    }

    /**
     * The bet levels currently open, honouring the admin override.
     */
    public static function currentAllowedBets(): array
    {
        if (! Setting::bool('tables_auto_mode')) {
            $manual = json_decode((string) Setting::get('tables_manual_bets', '["500","1000"]'), true);
            if (is_array($manual) && $manual !== []) {
                return array_values(array_map('intval', $manual));
            }
            // Garbage config must never close every table.
            return [500, 1000];
        }

        return self::allowedBets(self::onlineCount());
    }
}
