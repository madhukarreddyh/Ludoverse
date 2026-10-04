<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The license install gate. NOTE: the license is NOT activated in setUp —
 * each test controls activation explicitly.
 */
class LicenseGateTest extends TestCase
{
    use RefreshDatabase;

    protected function seedLicense(array $overrides = []): License
    {
        return License::create(array_merge([
            'license_key' => 'TESTKEY123',
            'email' => 'owner@example.com',
            'status' => 'active',
        ], $overrides));
    }

    public function test_unlicensed_requests_redirect_to_install(): void
    {
        $this->get('/')->assertRedirect(route('install.show'));
        $this->get('/about')->assertRedirect(route('install.show'));
        $this->get('/contact')->assertRedirect(route('install.show'));
        $this->get('/login')->assertRedirect(route('install.show'));
        $this->get('/signup')->assertRedirect(route('install.show'));
    }

    public function test_install_page_is_reachable_when_unlicensed(): void
    {
        $this->get('/install')->assertOk()->assertSee('Install LudoVerse');
    }

    public function test_wrong_license_key_is_rejected(): void
    {
        $this->seedLicense();

        $response = $this->post('/install', [
            'license_key' => 'WRONGKEY',
            'email' => 'owner@example.com',
        ]);

        $response->assertSessionHasErrors('license_key');
        $this->assertFalse(Setting::bool('license_activated'));
    }

    public function test_wrong_email_is_rejected(): void
    {
        $this->seedLicense();

        $response = $this->post('/install', [
            'license_key' => 'TESTKEY123',
            'email' => 'someone-else@example.com',
        ]);

        $response->assertSessionHasErrors('license_key');
        $this->assertFalse(Setting::bool('license_activated'));
    }

    public function test_disabled_license_is_rejected(): void
    {
        $this->seedLicense(['status' => 'disabled']);

        $response = $this->post('/install', [
            'license_key' => 'TESTKEY123',
            'email' => 'owner@example.com',
        ]);

        $response->assertSessionHasErrors('license_key');
        $this->assertFalse(Setting::bool('license_activated'));
    }

    public function test_correct_key_and_email_activates_platform(): void
    {
        $this->seedLicense();

        // Email match is case-insensitive.
        $response = $this->post('/install', [
            'license_key' => 'TESTKEY123',
            'email' => 'OWNER@EXAMPLE.COM',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertTrue(Setting::bool('license_activated'));
    }

    public function test_pages_are_reachable_once_licensed(): void
    {
        $this->seedLicense();

        $this->post('/install', [
            'license_key' => 'TESTKEY123',
            'email' => 'owner@example.com',
        ]);

        $this->get('/')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/privacy-policy')->assertOk();
        $this->get('/refund-policy')->assertOk();
        $this->get('/terms-and-conditions')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/signup')->assertOk();
    }

    public function test_install_redirects_home_when_already_licensed(): void
    {
        Setting::set('license_activated', '1');

        $this->get('/install')->assertRedirect(route('home'));
    }
}
