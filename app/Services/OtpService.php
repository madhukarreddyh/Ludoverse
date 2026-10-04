<?php

namespace App\Services;

use App\Models\Otp;
use Illuminate\Support\Facades\Hash;

/**
 * Mobile OTP issuance and verification.
 *
 * Only the bcrypt hash of the 6-digit code is stored in the database.
 * The plain code is never returned in an HTTP response; in the testing
 * environment it is cached so feature tests can read it back.
 */
class OtpService
{
    public const CODE_LENGTH = 6;

    public const EXPIRY_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /**
     * Issue a fresh OTP for a mobile number.
     *
     * @throws OtpResendTooSoon when the previous code was sent < 60s ago.
     */
    public function send(string $mobile): void
    {
        $latest = Otp::where('mobile', $mobile)->latest()->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            throw new OtpResendTooSoon('Please wait a minute before requesting a new code.');
        }

        $code = (string) random_int(10 ** (self::CODE_LENGTH - 1), (10 ** self::CODE_LENGTH) - 1);

        Otp::create([
            'mobile' => $mobile,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
        ]);

        // Testing-only backdoor so feature tests can complete the flow.
        if (app()->environment('testing')) {
            cache()->put($this->cacheKey($mobile), $code, now()->addMinutes(self::EXPIRY_MINUTES));
        }

        // Production SMS dispatch would be wired here (Phase: notifications).
    }

    /**
     * Verify a submitted code. Returns null on success, or an error message.
     */
    public function verify(string $mobile, string $code): ?string
    {
        $otp = Otp::where('mobile', $mobile)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($otp === null) {
            return 'No valid code found. Please request a new one.';
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            return 'Too many attempts. Please request a new code.';
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return 'The code you entered is incorrect.';
        }

        // Single-use: burn the code so it cannot be replayed.
        $otp->delete();

        return null;
    }

    /**
     * Read the last issued plain code — testing environment only.
     * Aborts with 403 anywhere else.
     */
    public function plainCodeForTesting(string $mobile): ?string
    {
        abort_unless(app()->environment('testing'), 403);

        return cache()->get($this->cacheKey($mobile));
    }

    protected function cacheKey(string $mobile): string
    {
        return 'otp_plain_'.preg_replace('/[^0-9]/', '', $mobile);
    }
}
