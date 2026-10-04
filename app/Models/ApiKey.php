<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * API keys for the public (player) and private (partner) APIs. Only the
 * sha256 hash is stored — the plain key is shown ONCE at generation.
 */
class ApiKey extends Model
{
    protected $fillable = ['name', 'key_prefix', 'key_hash', 'type', 'ip_whitelist', 'is_active', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'ip_whitelist' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiKeyLog::class, 'key_id');
    }

    /**
     * Generate a new key pair. Returns [model, plainKey] — the plain key
     * must be displayed to the admin immediately and never stored.
     */
    public static function generate(string $name, string $type, ?array $ipWhitelist = null): array
    {
        $prefix = $type === 'private' ? 'lv_priv_' : 'lv_pub_';
        $plain = $prefix.Str::random(40);

        $key = static::create([
            'name' => $name,
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'type' => $type,
            'ip_whitelist' => $ipWhitelist,
            'is_active' => true,
        ]);

        return [$key, $plain];
    }

    /**
     * Find an active key by its Bearer value, or null.
     */
    public static function findByBearer(string $bearer): ?self
    {
        $candidate = static::where('key_prefix', substr($bearer, 0, 12))
            ->where('is_active', true)
            ->first();

        if (! $candidate || ! hash_equals($candidate->key_hash, hash('sha256', $bearer))) {
            return null;
        }

        return $candidate;
    }

    /**
     * IP whitelist check for private keys. An empty whitelist means
     * "no restriction" — but partner keys SHOULD always set one, since
     * debit/credit is a powerful capability.
     */
    public function ipAllowed(?string $ip): bool
    {
        $list = $this->ip_whitelist ?? [];
        if ($list === []) {
            return true;
        }

        return $ip !== null && in_array($ip, $list, true);
    }
}
