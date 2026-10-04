<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'username', 'email', 'mobile', 'password',
    'referral_code', 'my_referral_code',
    'email_verified_at', 'mobile_verified_at',
    'terms_accepted_at', 'privacy_accepted_at', 'refund_accepted_at',
    'role', 'status', 'wallet_balance_paise',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'refund_accepted_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance_paise' => 'integer',
        ];
    }

    /**
     * The user's full ledger (the balance source of truth).
     */
    public function walletEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    /**
     * Convenience check used by the `admin` middleware.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
