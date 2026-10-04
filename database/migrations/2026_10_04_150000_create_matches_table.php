<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single Ludo contest. Money is only touched at start (bet debit)
     * and at finish (commission + winner payouts); everything in between
     * is pure game state in board_state / scores.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            // 1v1 = 2 players / 2 teams of 1, 2v2 = 4 / 2x2, 3v3 = 6 / 2x3, 4v4 = 8 / 2x4.
            $table->enum('mode', ['1v1', '2v2', '3v3', '4v4']);
            $table->unsignedInteger('bet_paise');
            $table->enum('status', ['waiting', 'running', 'finished', 'cancelled'])->default('waiting');
            // {color: [token0..token3]} — token = -1 base, 0..50 track steps,
            // 51..55 home stretch, 56 home. See LudoEngine for the encoding.
            $table->json('board_state')->nullable();
            // {user_id: score} — the authoritative per-player rush score.
            $table->json('scores')->nullable();
            $table->foreignId('current_turn_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('turn_deadline_at')->nullable();
            // Dice from the last server roll, awaiting the player's move choice.
            $table->unsignedTinyInteger('pending_dice')->nullable();
            // Consecutive sixes this turn — three in a row forfeits the turn.
            $table->unsignedTinyInteger('consecutive_sixes')->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('winning_team')->nullable();
            $table->timestamps();

            $table->index(['status', 'mode', 'bet_paise']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
