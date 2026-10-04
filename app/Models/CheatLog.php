<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only anti-cheat flag. Types: impossible_score,
 * client_version_mismatch, impossible_move_timing, ...
 */
class CheatLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'match_id', 'type', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(LudoMatch::class, 'match_id');
    }
}
