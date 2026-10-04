<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — tournament engine (IPL format).
     *
     * Prize split (documented here and in TournamentService): the prize
     * pool is total entry fees minus the tournament_commission_rate
     * setting (default 10%). Of the pool: champion 60%, runner-up 25%,
     * third place 15%. In 4v4 each tier is split equally among the
     * winning side's users.
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('mode', ['1v1', '4v4']);
            $table->unsignedInteger('entry_fee_paise')->default(0);
            $table->unsignedInteger('max_participants');
            $table->enum('status', [
                'upcoming', 'league', 'qualifier', 'semifinal',
                'final', 'completed', 'cancelled',
            ])->default('upcoming');
            $table->timestamp('starts_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tournament_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->enum('status', ['active', 'eliminated'])->default('active');
            $table->timestamps();

            $table->unique(['tournament_id', 'user_id']);
            $table->index(['tournament_id', 'points']);
        });

        Schema::create('tournament_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->enum('stage', [
                'league', 'qualifier1', 'eliminator', 'qualifier2',
                'semifinal', 'final',
            ]);
            // The real Ludo contest (bet 0 — entry already paid).
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            // 1v1 sides.
            $table->foreignId('participant1_id')->nullable()
                ->constrained('tournament_participants')->nullOnDelete();
            $table->foreignId('participant2_id')->nullable()
                ->constrained('tournament_participants')->nullOnDelete();
            // 1v1 join tracking.
            $table->timestamp('participant1_joined_at')->nullable();
            $table->timestamp('participant2_joined_at')->nullable();
            // 4v4 sides: arrays of user ids (max 4 each, min 3 to play).
            $table->json('side1_user_ids')->nullable();
            $table->json('side2_user_ids')->nullable();
            // 4v4: 1 or 2 (which side won); 1v1 uses winner_participant_id.
            $table->unsignedTinyInteger('winner_side')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('join_deadline_at')->nullable();
            $table->enum('status', ['pending', 'ongoing', 'completed'])->default('pending');
            $table->foreignId('winner_participant_id')->nullable()
                ->constrained('tournament_participants')->nullOnDelete();
            $table->timestamps();

            $table->index(['tournament_id', 'stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_fixtures');
        Schema::dropIfExists('tournament_participants');
        Schema::dropIfExists('tournaments');
    }
};
