<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;

class AdminLicenseTest extends LicensedTestCase
{
    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function regularUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/hmkr/licenses')->assertRedirect('/login');
    }

    public function test_non_admin_gets_403(): void
    {
        $this->actingAs($this->regularUser())->get('/hmkr/licenses')->assertForbidden();
        $this->actingAs($this->regularUser())->post('/hmkr/licenses', ['email' => 'x@y.z'])->assertForbidden();
    }

    public function test_admin_can_list_licenses(): void
    {
        License::create(['license_key' => 'ABC123', 'email' => 'a@b.c', 'status' => 'active']);

        $response = $this->actingAs($this->admin())->get('/hmkr/licenses');

        $response->assertOk()->assertSee('ABC123');
    }

    public function test_admin_can_generate_license(): void
    {
        $response = $this->actingAs($this->admin())->post('/hmkr/licenses', [
            'email' => 'customer@example.com',
        ]);

        $response->assertRedirect();
        $license = License::where('email', 'customer@example.com')->first();
        $this->assertNotNull($license);
        $this->assertNotEmpty($license->license_key);
        $this->assertSame('active', $license->status);
    }

    public function test_admin_can_disable_and_reactivate_license(): void
    {
        $license = License::create(['license_key' => 'ABC123', 'email' => 'a@b.c', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->patch(route('hmkr.licenses.disable', $license))
            ->assertRedirect();
        $this->assertSame('disabled', $license->fresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('hmkr.licenses.activate', $license))
            ->assertRedirect();
        $this->assertSame('active', $license->fresh()->status);
    }

    public function test_admin_can_delete_license(): void
    {
        $license = License::create(['license_key' => 'ABC123', 'email' => 'a@b.c', 'status' => 'active']);

        $this->actingAs($this->admin())
            ->delete(route('hmkr.licenses.destroy', $license))
            ->assertRedirect();

        $this->assertDatabaseMissing('licenses', ['license_key' => 'ABC123']);
    }

    public function test_admin_settings_page_requires_admin(): void
    {
        $this->actingAs($this->regularUser())->get('/hmkr/settings')->assertForbidden();
        $this->actingAs($this->admin())->get('/hmkr/settings')->assertOk();
    }
}
