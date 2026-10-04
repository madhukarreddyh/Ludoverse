<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Collusion / risk suspicion triaged by admins.
 * Types: same_ip_match, same_device_match, repeated_opponent,
 * intentional_loss, high_winrate, vpn_suspect.
 */
class FraudFlag extends Model
{
    public $timestamps = false;

    public const TYPES = [
        'same_ip_match', 'same_device_match', 'repeated_opponent',
        'intentional_loss', 'high_winrate', 'vpn_suspect',
    ];

    protected $fillable = ['user_id', 'type', 'details', 'status', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
