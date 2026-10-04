<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends LicensedTestCase
{
    /** @return array<string, mixed> */
    protected function validSignupData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Player',
            'username' => 'testplayer',
            'email' => 'player@example.com',
            'mobile' => '9876543210',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'terms' => '1',
            'privacy' => '1',
            'refund' => '1',
        ], $overrides);
    }

    public function test_signup_creates_user_and_logs_in(): void
    {
        $response = $this->post('/signup', $this->validSignupData());

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'player@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('testplayer', $user->username);
        $this->assertNotNull($user->my_referral_code);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertNotNull($user->refund_accepted_at);
        $this->assertSame('user', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_signup_rejects_bad_email(): void
    {
        $response = $this->post('/signup', $this->validSignupData(['email' => 'not-an-email']));

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_rejects_weak_password(): void
    {
        // Too short and no numbers.
        $response = $this->post('/signup', $this->validSignupData([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_rejects_password_without_numbers(): void
    {
        $response = $this->post('/signup', $this->validSignupData([
            'password' => 'NoNumbersHere',
            'password_confirmation' => 'NoNumbersHere',
        ]));

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_signup_rejects_missing_policy_acceptance(): void
    {
        $data = $this->validSignupData();
        unset($data['terms'], $data['privacy'], $data['refund']);

        $response = $this->post('/signup', $data);

        $response->assertSessionHasErrors(['terms', 'privacy', 'refund']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'testplayer']);

        $response = $this->post('/signup', $this->validSignupData());

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_succeeds_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'player@example.com',
            'username' => 'testplayer',
        ]);

        $response = $this->post('/login', [
            'login' => 'player@example.com',
            'password' => 'password', // factory default
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_succeeds_with_username(): void
    {
        $user = User::factory()->create([
            'email' => 'player@example.com',
            'username' => 'testplayer',
        ]);

        $response = $this->post('/login', [
            'login' => 'testplayer',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'player@example.com']);

        $response = $this->post('/login', [
            'login' => 'player@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
