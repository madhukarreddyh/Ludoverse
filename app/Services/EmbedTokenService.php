<?php

namespace App\Services;

use App\Models\ApiKey;

/**
 * Signed tokens for secure iframe embedding by partners.
 *
 * Flow: a partner calls EmbedTokenService::issue($apiKey, $ttl) server-side
 * and drops the token into their page's iframe src
 * (/embed/match/{id}?token=...). The embed endpoint verifies the HMAC
 * before rendering. The token proves "this embed was authorized by the
 * holder of this API key within the last $ttl seconds" — the partner's
 * site never receives game logic, only the board state the server
 * broadcasts (the same spectator-safe payload as /play/match/{id}/watch).
 *
 * Token format: base64url(payload).base64url(hmac_sha256(payload, key))
 * where payload = {"kid": keyId, "exp": unixTimestamp}.
 * The HMAC secret is the partner's FULL api key (never stored server-side
 * in plain — but the issuer HAS it at issue time; verification re-derives
 * it from the key id's stored hash? No — we can't. See verify()).
 *
 * IMPLEMENTATION NOTE: because only the key HASH is stored, verification
 * cannot recompute an HMAC keyed by the api key itself. Instead the HMAC
 * is keyed by the app key + key id (both server secrets), and the payload
 * binds the token to the key id. Stealing a token is useless after expiry;
 * stealing the app key is already game-over. This is documented on the
 * embedding docs page so partners know the exact trust model.
 */
class EmbedTokenService
{
    /**
     * Issue a signed embed token for an API key, valid $ttlSeconds.
     */
    public function issue(ApiKey $apiKey, int $ttlSeconds = 3600): string
    {
        $payload = json_encode([
            'kid' => $apiKey->id,
            'exp' => time() + max(60, $ttlSeconds),
        ]);

        $encoded = $this->b64encode($payload);
        $sig = $this->b64encode(hash_hmac('sha256', $encoded, $this->secretFor($apiKey), true));

        return $encoded.'.'.$sig;
    }

    /**
     * Verify a token. Returns the ApiKey it was issued for, or null.
     */
    public function verify(string $token): ?ApiKey
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $sig] = $parts;

        $payload = json_decode($this->b64decode($encoded), true);
        if (! is_array($payload) || ! isset($payload['kid'], $payload['exp'])) {
            return null;
        }
        if (! is_int($payload['exp']) || $payload['exp'] < time()) {
            return null; // expired
        }

        $apiKey = ApiKey::find($payload['kid']);
        if (! $apiKey || ! $apiKey->is_active) {
            return null;
        }

        $expected = $this->b64encode(hash_hmac('sha256', $encoded, $this->secretFor($apiKey), true));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        return $apiKey;
    }

    /**
     * Server-side HMAC secret binding a token to a key id.
     */
    protected function secretFor(ApiKey $apiKey): string
    {
        return hash_hmac('sha256', 'embed:'.$apiKey->id.':'.$apiKey->key_hash, (string) config('app.key'));
    }

    protected function b64encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    protected function b64decode(string $input): string
    {
        $padded = strtr($input, '-_', '+/').str_repeat('=', (4 - strlen($input) % 4) % 4);

        return (string) base64_decode($padded, true);
    }
}
