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
