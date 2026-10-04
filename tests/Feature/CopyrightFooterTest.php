<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;

class CopyrightFooterTest extends LicensedTestCase
{
    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_footer_shows_default_copyright_text(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Copyright © LudoVerse Platform. All rights reserved.');
    }

    public function test_footer_toggle_hides_copyright(): void
    {
        $this->actingAs($this->admin())->post(route('hmkr.settings.update'), [
            'copyright_text' => 'Copyright © LudoVerse Platform. All rights reserved.',
            'show_copyright' => '0',
        ])->assertRedirect();

        $this->assertFalse(Setting::bool('show_copyright'));

        $response = $this->get('/');
        $response->assertOk();
        $response->assertDontSee('Copyright © LudoVerse Platform. All rights reserved.');
    }

    public function test_admin_can_edit_copyright_text(): void
    {
        $this->actingAs($this->admin())->post(route('hmkr.settings.update'), [
            'copyright_text' => '© 2026 My Custom Studio. All rights reserved.',
            'show_copyright' => '1',
        ])->assertRedirect();

        $this->assertSame(
            '© 2026 My Custom Studio. All rights reserved.',
            Setting::get('copyright_text')
        );

        $this->get('/')->assertSee('© 2026 My Custom Studio. All rights reserved.');
    }

    public function test_non_admin_cannot_change_settings(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post(route('hmkr.settings.update'), [
            'copyright_text' => 'Hacked text',
            'show_copyright' => '1',
        ])->assertForbidden();

        $this->assertSame(
            'Copyright © LudoVerse Platform. All rights reserved.',
            Setting::get('copyright_text')
        );
    }
}
