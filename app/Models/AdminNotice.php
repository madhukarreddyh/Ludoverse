<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * System-raised notices surfaced on the admin dashboard
 * (e.g. the risk manager raising bot difficulty).
 */
class AdminNotice extends Model
{
    protected $fillable = ['type', 'title', 'body', 'is_read'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean'];
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
