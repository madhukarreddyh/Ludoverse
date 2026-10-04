<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per relationship. pair_key ("min_id:max_id") is UNIQUE so the
 * same pair can never hold two rows in either direction.
 */
class Friendship extends Model
{
    protected $fillable = [
        'requester_id', 'addressee_id', 'pair_key', 'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Friendship $friendship) {
            $a = (int) $friendship->requester_id;
            $b = (int) $friendship->addressee_id;
            $friendship->pair_key = min($a, $b).':'.max($a, $b);
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * The friendship row between two users, if any (either direction).
     */
    public static function between(int $a, int $b): ?self
    {
        return static::where('pair_key', min($a, $b).':'.max($a, $b))->first();
    }

    public static function areFriends(int $a, int $b): bool
    {
        return static::where('pair_key', min($a, $b).':'.max($a, $b))
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * The user on the other end of this friendship for $userId.
     */
    public function otherUserId(int $userId): int
    {
        return (int) $this->requester_id === $userId
            ? (int) $this->addressee_id
            : (int) $this->requester_id;
    }
}
