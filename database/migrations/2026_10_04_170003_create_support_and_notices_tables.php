<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support + admin notices (Phase 6):
     *  - support_tickets: player-raised tickets; admin_reply holds the
     *    latest staff answer, support_ticket_replies holds the full
     *    thread (including instant chatbot replies, is_bot = true).
     *  - admin_notices: system-raised notices for the admin dashboard
     *    (e.g. risk scan raising bot difficulty).
     */
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 150);
            $table->text('message');
            $table->enum('status', ['open', 'answered', 'closed'])->default('open');
            $table->text('admin_reply')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_bot')->default(false);
            $table->timestamps();

            $table->index('ticket_id');
        });

        Schema::create('admin_notices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notices');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
