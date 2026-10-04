<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anti-cheat + anti-collusion + bonus-abuse (Phase 6):
     *  - cheat_logs: append-only record of every anti-cheat flag.
     *  - fraud_flags: collusion / win-rate / VPN suspicions, triaged by
     *    admins (open -> confirmed / dismissed).
     *  - bonus_locks: referral-bonus money that is NOT spendable until the
     *    player has wagered required_wager_paise.
     */
    public function up(): void
    {
        Schema::create('cheat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('type', 64);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', [
                'same_ip_match', 'same_device_match', 'repeated_opponent',
                'intentional_loss', 'high_winrate', 'vpn_suspect',
            ]);
            $table->json('details')->nullable();
            $table->enum('status', ['open', 'confirmed', 'dismissed'])->default('open');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('bonus_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ledger_id')->constrained('wallet_ledgers')->cascadeOnDelete();
            $table->unsignedBigInteger('required_wager_paise');
            $table->boolean('released')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'released']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_locks');
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('cheat_logs');
    }
};
