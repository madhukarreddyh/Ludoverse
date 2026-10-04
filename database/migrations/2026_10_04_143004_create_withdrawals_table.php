<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withdrawal requests. On request the full amount is debited from the
 * ledger immediately (type 'withdrawal', negative) to hold the funds, then:
 *   approve  → status only (funds already held)
 *   paid     → status only (operator has sent the money)
 *   reject   → compensating ledger entry returns the held funds.
 *
 * Breakdown math (commented in WithdrawalBreakdown):
 *   commission = round(amount * withdrawal_commission_rate / 100)
 *   tds        = round(amount * tds_rate / 100)   // 30% default on winnings
 *   net        = amount - tds - commission        // what the user receives
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_paise');
            $table->string('method'); // upi | bank
            // upi: {upi_id}; bank: {account_no, ifsc, account_name}
            $table->json('details');
            $table->bigInteger('tds_paise');
            $table->bigInteger('commission_paise');
            $table->bigInteger('net_paise');
            $table->string('status')->default('pending'); // pending | approved | rejected | paid
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
