<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway (Razorpay/Paytm) deposit attempts. A row is created BEFORE the
 * gateway checkout opens; the payment callback flips it to completed and
 * only then is money credited to the ledger (idempotent on
 * gateway_payment_id — duplicate callbacks never double-credit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway'); // razorpay | paytm
            $table->string('gateway_order_id')->unique();
            $table->bigInteger('amount_paise');
            $table->string('status')->default('pending'); // pending | completed | failed
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
