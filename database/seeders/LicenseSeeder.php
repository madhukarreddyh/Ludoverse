<?php

namespace Database\Seeders;

use App\Models\License;
use Illuminate\Database\Seeder;

class LicenseSeeder extends Seeder
{
    /**
     * Seed the spec's example development license.
     */
    public function run(): void
    {
        License::updateOrCreate(
            ['license_key' => 'MADHUKARREDDY'],
            [
                'email' => 'madhukarreddy1237@gmail.com',
                'status' => 'active',
            ],
        );
    }
}
