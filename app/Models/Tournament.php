<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    protected $fillable = [
        'name', 'mode', 'entry_fee_paise', 'max_participants',
        'status', 'starts_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_paise' => 'integer',
            'max_participants' => 'integer',
            'starts_at' => 'datetime',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentFixture::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Points table: points desc, then wins desc, then earliest entry.
     */
    public function standings()
    {
        return $this->participants()
            ->orderByDesc('points')
            ->orderByDesc('wins')
            ->orderBy('id')
            ->get();
    }

    public function isOpenForRegistration(): bool
    {
        return $this->status === 'upcoming'
            && $this->participants()->count() < $this->max_participants;
    }
}
