<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One seat at a match table. Bots have user_id = null and is_bot = true —
 * they play but never touch the wallet.
 */
class MatchPlayer extends Model
{
    protected $fillable = [
        'match_id', 'user_id', 'team', 'color', 'is_bot', 'bot_difficulty',
        'score', 'missed_turns', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'score' => 'integer',
            'missed_turns' => 'integer',
            'team' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(LudoMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHuman(): bool
    {
        return ! $this->is_bot && $this->user_id !== null;
    }
}
