<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base class for feature tests that assume the platform license is active.
 * The license gate itself is covered separately in LicenseGateTest.
 */
abstract class LicensedTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('license_activated', '1');
    }
}
