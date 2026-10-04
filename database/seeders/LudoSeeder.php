<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ludo tables need two kinds of non-human users:
 * - `platform` (role system): receives match commission.
 * - bot_1..bot_5 (role bot): fill seats for bot play.
 * Bots hold no money and never touch the wallet.
 */
class LudoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'platform'],
            [
                'name' => 'Platform',
                'email' => 'platform@ludoverse.local',
                'password' => Str::random(32),
                'role' => 'system',
                'status' => 'active',
                'my_referral_code' => 'PLATFORM',
                'email_verified_at' => now(),
            ],
        );

        for ($i = 1; $i <= 5; $i++) {
            User::firstOrCreate(
                ['username' => "bot_{$i}"],
                [
                    'name' => "Bot {$i}",
                    'email' => "bot_{$i}@ludoverse.local",
                    'password' => Str::random(32),
                    'role' => 'bot',
                    'status' => 'active',
                    'my_referral_code' => 'BOT'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
