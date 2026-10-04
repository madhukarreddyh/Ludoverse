<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id', 'amount_paise', 'method', 'details',
        'tds_paise', 'commission_paise', 'net_paise',
        'status', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'details' => 'array',
            'tds_paise' => 'integer',
            'commission_paise' => 'integer',
            'net_paise' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
