<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One (user, device_hash) link. A device_hash is the SHA-256 of client
 * fingerprint params (browser, OS, screen, WebGL renderer, timezone,
 * hardwareConcurrency) collected by a small JS snippet on signup/login.
 */
class Device extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'device_hash', 'ip_address', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isBanned(): bool
    {
        return BannedDevice::where('device_hash', $this->device_hash)->exists();
    }
}
