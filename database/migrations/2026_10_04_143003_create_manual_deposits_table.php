<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual UPI/bank deposits: the user pays to the platform UPI ID shown on
 * the deposit page and submits the amount + UTR + screenshot. An admin
 * approves (credits the ledger) or rejects (nothing moves).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_paise');
            // UTR (UPI transaction reference): one claim per transaction.
            $table->string('utr')->unique();
            $table->string('screenshot_path');
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_deposits');
    }
};
