<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A referral-bonus credit that is NOT spendable until the player has
 * wagered required_wager_paise. Available balance = ledger sum minus the
 * sum of unreleased locks (computed in WalletService::availableBalance).
 */
class BonusLock extends Model
{
    protected $fillable = ['user_id', 'ledger_id', 'required_wager_paise', 'released'];

    protected function casts(): array
    {
        return ['released' => 'boolean', 'required_wager_paise' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(WalletLedger::class, 'ledger_id');
    }
}
