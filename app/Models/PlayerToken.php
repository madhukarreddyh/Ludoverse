<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Short-lived Bearer tokens for players, issued by POST
 * /api/v1/public/player/login (game_id or email+password).
 */
class PlayerToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Issue a token valid for 24h. Returns [model, plainToken].
     */
    public static function issue(User $user): array
    {
        $plain = 'lv_player_'.Str::random(48);

        $token = static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return [$token, $plain];
    }

    public static function findByBearer(string $bearer): ?self
    {
        $token = static::where('token_hash', hash('sha256', $bearer))->first();

        if (! $token || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }
}
