<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the default admin user. The password comes from the
     * ADMIN_SEED_PASSWORD environment variable so no secret is
     * committed to the repository.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ludoverse.local'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make(env('ADMIN_SEED_PASSWORD', 'ChangeMe123!')),
                'my_referral_code' => 'ADMIN001',
                'email_verified_at' => now(),
                'role' => 'admin',
                'status' => 'active',
            ],
        );
    }
}
