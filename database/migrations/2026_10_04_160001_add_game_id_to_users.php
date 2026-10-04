<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — friends + online presence:
     * - game_id: public friend code ("LV" + zero-padded id), unique.
     * - last_seen_at: bumped by middleware on authenticated requests;
     *   online = seen within the last 5 minutes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('game_id', 16)->nullable()->unique()->after('id');
            $table->timestamp('last_seen_at')->nullable()->after('remember_token');
        });

        // Backfill every existing row deterministically.
        DB::statement("UPDATE users SET game_id = CONCAT('LV', LPAD(id, 6, '0')) WHERE game_id IS NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['game_id']);
            $table->dropColumn(['game_id', 'last_seen_at']);
        });
    }
};
