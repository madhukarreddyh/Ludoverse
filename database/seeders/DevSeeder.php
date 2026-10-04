<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development-only seed data (licenses, admin user).
 * Never runs in the testing environment — tests set up their own data.
 */
class DevSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            LicenseSeeder::class,
            AdminUserSeeder::class,
            LudoSeeder::class,
        ]);
    }
}
