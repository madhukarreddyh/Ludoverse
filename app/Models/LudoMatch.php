<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Ludo contest. Named LudoMatch because `match` is a reserved keyword
 * in PHP 8+; the table itself is `matches`.
 */
class LudoMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'mode', 'bet_paise', 'status', 'board_state', 'scores',
        'current_turn_user_id', 'turn_deadline_at', 'pending_dice',
        'consecutive_sixes', 'ends_at', 'winner_user_id', 'winning_team',
    ];

    protected function casts(): array
    {
        return [
            'board_state' => 'array',
            'scores' => 'array',
            'turn_deadline_at' => 'datetime',
            'ends_at' => 'datetime',
            'pending_dice' => 'integer',
            'consecutive_sixes' => 'integer',
            'bet_paise' => 'integer',
            'winning_team' => 'integer',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    /**
     * Seats per mode: 1v1 -> 2, 2v2 -> 4, 3v3 -> 6, 4v4 -> 8.
     */
    public static function seatsForMode(string $mode): int
    {
        return match ($mode) {
            '1v1' => 2,
            '2v2' => 4,
            '3v3' => 6,
            '4v4' => 8,
            default => throw new \InvalidArgumentException("Unknown mode {$mode}."),
        };
    }

    /**
     * Match length per mode (rush format).
     */
    public static function durationMinutesForMode(string $mode): int
    {
        return match ($mode) {
            '1v1' => 5,
            '2v2' => 10,
            '3v3' => 10,
            '4v4' => 15,
            default => throw new \InvalidArgumentException("Unknown mode {$mode}."),
        };
    }
}
