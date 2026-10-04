<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Device security (Phase 6):
     *  - devices: one row per (user, device_hash). The pair is unique, so
     *    the same browser/device can be linked to many accounts ONLY if
     *    those accounts are not simultaneously active — the "one account
     *    per device" rule is enforced in DeviceService, not by schema.
     *  - banned_devices: a hash here blocks ALL signup/login attempts.
     *  - users.wagered_paise: lifetime bet turnover, drives bonus-lock
     *    release (bonus-abuse protection).
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_hash', 64);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'device_hash']);
            $table->index('device_hash');
        });

        Schema::create('banned_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_hash', 64)->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('wagered_paise')->default(0)->after('wallet_balance_paise');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wagered_paise');
        });
        Schema::dropIfExists('banned_devices');
        Schema::dropIfExists('devices');
    }
};
