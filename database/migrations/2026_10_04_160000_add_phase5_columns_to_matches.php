<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — bot economy + tournaments + private invites:
     * - is_private: invite-only tables stay out of public matchmaking and
     *   out of bot auto-fill.
     * - invited_user_id: the friend the private table was created for.
     * - tournament_fixture_id: links a bet-0 contest match back to its
     *   tournament fixture so results flow into the points table.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('bet_paise');
            $table->foreignId('invited_user_id')->nullable()->after('is_private')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('tournament_fixture_id')->nullable()->after('invited_user_id')
                ->constrained('tournament_fixtures')->nullOnDelete();
            $table->index(['status', 'is_private']);
        });

        Schema::table('match_players', function (Blueprint $table) {
            // Which strategy this bot seat plays with (easy/medium/hard).
            // Null = fall back to the bot_difficulty setting.
            $table->string('bot_difficulty', 16)->nullable()->after('is_bot');
        });
    }

    public function down(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('bot_difficulty');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_private']);
            $table->dropConstrainedForeignId('tournament_fixture_id');
            $table->dropConstrainedForeignId('invited_user_id');
            $table->dropColumn('is_private');
        });
    }
};
