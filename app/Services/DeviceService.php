<?php

namespace App\Services;

use App\Models\BannedDevice;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Device security: one account per device.
 *
 * The client collects fingerprint params (browser, OS, screen resolution,
 * WebGL renderer, timezone, hardwareConcurrency), SHA-256 hashes them and
 * posts `device_hash` with signup/login. The server then:
 *   1. rejects any hash present in banned_devices,
 *   2. rejects a hash already linked to a DIFFERENT active user,
 *   3. otherwise links (user_id, device_hash) for future checks.
 *
 * NOTE: device fingerprinting is a heuristic, not an identity proof.
 * A missing/empty hash (JS disabled, API clients) skips enforcement —
 * it must never hard-block legitimate users.
 */
class DeviceSecurityException extends \RuntimeException
{
}

class DeviceService
{
    /**
     * Validate a device hash for a signup/login attempt.
     *
     * @param User|null $user The user being authenticated (null on signup
     *                       pre-check, where only ban + conflict matter).
     *
     * @throws DeviceSecurityException with a human-readable reason.
     */
    public function check(?User $user, ?string $deviceHash, Request $request): void
    {
        $hash = $this->normalize($deviceHash);
        if ($hash === null) {
            return; // no fingerprint supplied — cannot enforce, allow
        }

        if (BannedDevice::where('device_hash', $hash)->exists()) {
            throw new DeviceSecurityException(
                'This device has been banned from LudoVerse.'
            );
        }

        // One account per device: the hash may only belong to OTHER users
        // if those users are NOT active (suspended/frozen accounts do not
        // count — their device is still tainted, see banDevice()).
        $conflict = Device::where('device_hash', $hash)
            ->when($user, fn ($q) => $q->where('user_id', '!=', $user->id))
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->exists();

        if ($conflict) {
            throw new DeviceSecurityException(
                'This device is already linked to another LudoVerse account. '.
                'Each device may only be used with one account.'
            );
        }
    }

    /**
     * Record the (user, device) link after a successful signup/login.
     */
    public function register(User $user, ?string $deviceHash, Request $request): void
    {
        $hash = $this->normalize($deviceHash);
        if ($hash === null) {
            return;
        }

        Device::firstOrCreate(
            ['user_id' => $user->id, 'device_hash' => $hash],
            [
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Ban every device hash linked to a user (used on auto-suspend).
     */
    public function banUserDevices(User $user, string $reason): int
    {
        $hashes = Device::where('user_id', $user->id)->pluck('device_hash')->unique();

        $count = 0;
        foreach ($hashes as $hash) {
            BannedDevice::firstOrCreate(
                ['device_hash' => $hash],
                ['reason' => $reason]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Accept only plausible SHA-256 hex digests; anything else is treated
     * as "no fingerprint" rather than failing the request.
     */
    protected function normalize(?string $hash): ?string
    {
        $hash = strtolower(trim((string) $hash));
        if ($hash === '' || ! preg_match('/^[0-9a-f]{64}$/', $hash)) {
            return null;
        }

        return $hash;
    }
}
