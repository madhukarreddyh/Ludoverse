<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — 4v4 join tracking: side{1,2}_user_ids are the ROSTER
     * (who may play — the league grouping or the snake-drafted knockout
     * side); side{1,2}_confirmed are the users who actually joined
     * before the deadline. Forfeits and match seats are decided on
     * confirmed counts (min 3 per side).
     */
    public function up(): void
    {
        Schema::table('tournament_fixtures', function (Blueprint $table) {
            $table->json('side1_confirmed')->nullable()->after('side2_user_ids');
            $table->json('side2_confirmed')->nullable()->after('side1_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_fixtures', function (Blueprint $table) {
            $table->dropColumn(['side1_confirmed', 'side2_confirmed']);
        });
    }
};
