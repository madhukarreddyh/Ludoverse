<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per seat at a match table. Bots are rows with user_id = null
     * and is_bot = true — they hold no money and never touch the wallet.
     */
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('team');
            $table->string('color', 16);
            $table->boolean('is_bot')->default(false);
            $table->integer('score')->default(0);
            $table->unsignedInteger('missed_turns')->default(0);
            $table->enum('status', ['playing', 'finished', 'lost'])->default('playing');
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
            $table->unique(['match_id', 'color']);
            $table->index(['match_id', 'team']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
