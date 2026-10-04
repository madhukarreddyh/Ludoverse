<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * The primary key is the setting name itself.
     */
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Defaults used when no row exists yet (e.g. fresh install / tests).
     */
    public const DEFAULTS = [
        'license_activated' => '0',
        'copyright_text' => 'Copyright © LudoVerse Platform. All rights reserved.',
        'show_copyright' => '1',
        // Wallet / payments (Phase 3).
        'gateway_razorpay_enabled' => '0',
        'gateway_paytm_enabled' => '0',
        'deposit_upi_id' => '',
        'deposit_qr_image' => '',
        'withdrawal_commission_rate' => '2',
        'tds_rate' => '30',
        // Ludo matches (Phase 4): platform cut of each pot, in percent.
        'match_commission_rate' => '10',
        // Bot economy (Phase 5).
        'bot_difficulty' => 'medium',
        'bot_fill_enabled' => '0',
        'bot_tables' => '["500","1000"]',
        'bot_max_per_match' => '1',
        'bot_join_after_seconds' => '20',
        // Tournaments (Phase 5): platform cut of the entry-fee pool, %.
        'tournament_commission_rate' => '10',
        // Minutes a fixture participant has to join their match.
        'tournament_join_deadline_minutes' => '60',
        // ---- Phase 6: security, anti-cheat, liquidity, risk ----
        // Expected client build version; /play JSON endpoints require the
        // X-Client-Version header to match (mismatch = cheat flag, not ban).
        'client_version' => '1.0.0',
        // A move submitted faster than this (ms) after the dice is logged
        // as impossible_move_timing.
        'cheat_min_move_ms' => '200',
        // Flags inside this window (hours) that auto-suspend an account.
        'cheat_flag_window_hours' => '24',
        'cheat_auto_suspend_flags' => '3',
        // Upper bound on captures assumed when computing the theoretical
        // max score per match (score anomaly check).
        'cheat_max_captures_per_match' => '20',
        // Referral bonus: credited only after the referee verifies mobile;
        // locked until the referee wagers bonus x this multiplier.
        'referral_bonus_paise' => '10000',
        'bonus_wager_multiplier' => '5',
        // Risk manager: withdrawals above deposits x this ratio trigger
        // an automatic bot-difficulty raise (capped at hard).
        'risk_wd_ratio' => '0.9',
        // Liquidity manager: '1' = bet levels follow online count
        // automatically; '0' = use tables_manual_bets (JSON array).
        'tables_auto_mode' => '1',
        'tables_manual_bets' => '["500","1000"]',
        // Maintenance mode: '1' serves 503 on public pages (/hmkr stays up).
        'maintenance_mode' => '0',
    ];

    /**
     * Read a setting value, falling back to the default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $row = static::query()->find($key);
        } catch (\Throwable) {
            // Table may not exist yet (fresh install before migrations).
            $row = null;
        }

        if ($row === null) {
            return $default ?? self::DEFAULTS[$key] ?? null;
        }

        return $row->value;
    }

    /**
     * Persist a setting value (creates the row if needed).
     */
    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Boolean convenience wrapper; treats '1' / 'true' as true.
     */
    public static function bool(string $key): bool
    {
        $value = static::get($key);

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
