<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\OtpService;

class OtpTest extends LicensedTestCase
{
    protected string $mobile = '9876543210';

    protected function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_otp_happy_path_verifies_mobile(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile])
            ->assertRedirect()
            ->assertSessionHas('status');

        // Testing-only helper reads the plain code (never in a response).
        $code = (new OtpService)->plainCodeForTesting($this->mobile);
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->actingAs($user)->post('/mobile/otp/verify', [
            'mobile' => $this->mobile,
            'code' => $code,
        ])->assertRedirect(route('home'));

        $user->refresh();
        $this->assertSame($this->mobile, $user->mobile);
        $this->assertNotNull($user->mobile_verified_at);

        // The code is single-use: it is gone from the database.
        $this->assertDatabaseCount('otps', 0);
    }

    public function test_otp_code_is_stored_hashed_not_plain(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile]);

        $code = (new OtpService)->plainCodeForTesting($this->mobile);
        $hash = Otp::where('mobile', $this->mobile)->value('code_hash');

        $this->assertNotSame($code, $hash);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile]);

        $this->actingAs($user)->post('/mobile/otp/verify', [
            'mobile' => $this->mobile,
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile]);

        // Age the code past its 10-minute expiry.
        Otp::where('mobile', $this->mobile)->update(['expires_at' => now()->subMinute()]);

        $code = (new OtpService)->plainCodeForTesting($this->mobile);

        $this->actingAs($user)->post('/mobile/otp/verify', [
            'mobile' => $this->mobile,
            'code' => $code,
        ])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_too_many_attempts_locks_the_code(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile]);
        $code = (new OtpService)->plainCodeForTesting($this->mobile);

        // Burn all 5 attempts with wrong codes.
        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->actingAs($user)->post('/mobile/otp/verify', [
                'mobile' => $this->mobile,
                'code' => '000000',
            ])->assertSessionHasErrors('code');
        }

        // Even the correct code is now rejected.
        $response = $this->actingAs($user)->post('/mobile/otp/verify', [
            'mobile' => $this->mobile,
            'code' => $code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertStringContainsString(
            'Too many attempts',
            session('errors')->get('code')[0]
        );
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_resend_rate_limit_one_per_minute(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile])
            ->assertSessionHas('status');

        // Immediate resend is rejected with 429.
        $this->actingAs($user)->post('/mobile/otp/send', ['mobile' => $this->mobile])
            ->assertStatus(429);
    }

    public function test_guest_cannot_use_otp_endpoints(): void
    {
        $this->get('/mobile/verify')->assertRedirect('/login');
        $this->post('/mobile/otp/send', ['mobile' => $this->mobile])->assertRedirect('/login');
    }
}
