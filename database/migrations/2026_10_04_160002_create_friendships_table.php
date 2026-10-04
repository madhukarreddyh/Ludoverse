<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5 — friends. One row per relationship; pair_key
     * ("min_id:max_id") carries the UNIQUE constraint so the same pair
     * can never have two rows in either direction.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            // Canonical unordered pair key, e.g. "12:45".
            $table->string('pair_key', 32);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'blocked'])
                ->default('pending');
            $table->timestamps();

            $table->unique('pair_key');
            $table->index(['requester_id', 'status']);
            $table->index(['addressee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
