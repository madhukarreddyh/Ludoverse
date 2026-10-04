<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API platform (Phase 6):
     *  - api_keys: only the sha256 hash is stored; the plain key is shown
     *    ONCE at generation time and never again. key_prefix (first 12
     *    chars) lets us look the key up without scanning hashes.
     *  - api_key_logs: request audit trail per key.
     *  - player_tokens: short-lived Bearer tokens issued by the public
     *    API's player/login endpoint (game_id or email+password).
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('key_prefix', 16);
            $table->string('key_hash', 64)->unique();
            $table->enum('type', ['public', 'private']);
            $table->json('ip_whitelist')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('key_prefix');
        });

        Schema::create('api_key_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->string('endpoint', 255);
            $table->string('ip', 45)->nullable();
            $table->unsignedSmallInteger('status');
            $table->timestamp('created_at')->nullable();

            $table->index(['key_id', 'created_at']);
        });

        Schema::create('player_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_tokens');
        Schema::dropIfExists('api_key_logs');
        Schema::dropIfExists('api_keys');
    }
};
