<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualDeposit extends Model
{
    protected $fillable = [
        'user_id', 'amount_paise', 'utr', 'screenshot_path',
        'status', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
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
