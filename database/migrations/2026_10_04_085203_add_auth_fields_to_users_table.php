<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('mobile')->unique()->nullable()->after('email');
            // The referral code the user signed up WITH (may not belong to anyone).
            $table->string('referral_code')->nullable()->after('mobile');
            // The user's own referral code, handed out to others.
            $table->string('my_referral_code')->unique()->after('referral_code');
            $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('mobile_verified_at');
            $table->timestamp('privacy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('refund_accepted_at')->nullable()->after('privacy_accepted_at');
            $table->string('role')->default('user')->after('refund_accepted_at');
            $table->string('status')->default('active')->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'mobile', 'referral_code', 'my_referral_code',
                'mobile_verified_at', 'terms_accepted_at', 'privacy_accepted_at',
                'refund_accepted_at', 'role', 'status',
            ]);
        });
    }
};
