<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test HTTP call carries the current client version, so the
     * /play client-integrity middleware passes by default. Tests that
     * exercise version mismatch override the header explicitly.
     */
    protected $defaultHeaders = [
        'X-Client-Version' => '1.0.0',
    ];
}
