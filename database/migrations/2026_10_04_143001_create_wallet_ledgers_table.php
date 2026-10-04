<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wallet ledger is the SOURCE OF TRUTH for every user's balance.
 * Nothing reads or writes money except through this table (see WalletService).
 * users.wallet_balance_paise is a cached copy for fast reads only — it must
 * always equal SUM(amount_paise) of this table (see `wallet:reconcile`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cached balance column on users (read optimization only).
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('wallet_balance_paise')->default(0)->after('status');
        });

        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Debits are negative, credits are positive (signed).
            $table->string('transaction_type');
            $table->bigInteger('amount_paise');
            // Balance snapshot before/after this entry (audit trail).
            $table->bigInteger('previous_balance_paise');
            $table->bigInteger('new_balance_paise');
            // Idempotency key: one entry per reference, ever (unique).
            $table->string('reference_id')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledgers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_balance_paise');
        });
    }
};
