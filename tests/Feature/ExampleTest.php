<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The license gate redirects every web route to /install
     * until the platform license is activated.
     */
    public function test_unlicensed_application_redirects_to_install(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('install.show'));
    }
}
