<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class EmailVerificationTest extends LicensedTestCase
{
    public function test_unverified_user_sees_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertOk();
        $response->assertSee('Verify your email address');
    }

    public function test_verified_user_is_redirected_from_notice(): void
    {
        $user = User::factory()->create(); // verified by default

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertRedirect(route('home'));
    }

    public function test_valid_signed_url_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->actingAs($user)->get($url);

        $response->assertRedirect(route('home'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]).'&tampered=1';

        $response = $this->actingAs($user)->get($url);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_guest_cannot_access_verification_routes(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
    }
}
