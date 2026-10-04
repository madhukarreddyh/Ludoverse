<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKeyLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['key_id', 'endpoint', 'ip', 'status', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'status' => 'integer'];
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'key_id');
    }
}
