<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    protected $fillable = [
        'user_id', 'gateway', 'gateway_order_id',
        'amount_paise', 'status', 'gateway_payment_id',
    ];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
