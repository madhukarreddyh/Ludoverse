<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedger extends Model
{
    protected $table = 'wallet_ledgers';

    // Ledger rows are append-only: created only via WalletService.
    protected $fillable = [
        'user_id', 'transaction_type', 'amount_paise',
        'previous_balance_paise', 'new_balance_paise',
        'reference_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'previous_balance_paise' => 'integer',
            'new_balance_paise' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
