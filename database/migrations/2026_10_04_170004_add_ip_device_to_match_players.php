<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anti-cheat / anti-collusion context on gameplay rows (Phase 6):
     *  - match_players.ip_address + device_hash: recorded at seat time so
     *    fraud:scan can spot two humans from the same IP/device at one
     *    table.
     *  - matches.dice_rolled_at: set by roll(), read by move() to detect
     *    impossible move timing (< cheat_min_move_ms after the dice).
     */
    public function up(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('bot_difficulty');
            $table->string('device_hash', 64)->nullable()->after('ip_address');
            $table->index('ip_address');
            $table->index('device_hash');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->timestamp('dice_rolled_at')->nullable()->after('pending_dice');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('dice_rolled_at');
        });
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['device_hash']);
            $table->dropColumn(['ip_address', 'device_hash']);
        });
    }
};
