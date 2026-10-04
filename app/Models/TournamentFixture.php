<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentFixture extends Model
{
    protected $fillable = [
        'tournament_id', 'stage', 'match_id',
        'participant1_id', 'participant2_id',
        'participant1_joined_at', 'participant2_joined_at',
        'side1_user_ids', 'side2_user_ids', 'winner_side',
        'side1_confirmed', 'side2_confirmed',
        'scheduled_at', 'join_deadline_at',
        'status', 'winner_participant_id',
    ];

    protected function casts(): array
    {
        return [
            'side1_user_ids' => 'array',
            'side2_user_ids' => 'array',
            'side1_confirmed' => 'array',
            'side2_confirmed' => 'array',
            'winner_side' => 'integer',
            'scheduled_at' => 'datetime',
            'join_deadline_at' => 'datetime',
            'participant1_joined_at' => 'datetime',
            'participant2_joined_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(LudoMatch::class, 'match_id');
    }

    public function participant1(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant1_id');
    }

    public function participant2(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant2_id');
    }

    public function winnerParticipant(): BelongsTo
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_participant_id');
    }

    /**
     * 4v4 side membership (arrays of user ids, possibly empty).
     */
    public function sideUserIds(int $side): array
    {
        return $side === 1
            ? ($this->side1_user_ids ?? [])
            : ($this->side2_user_ids ?? []);
    }

    /**
     * 4v4 confirmed joins per side (who actually showed up).
     */
    public function confirmedUserIds(int $side): array
    {
        return $side === 1
            ? ($this->side1_confirmed ?? [])
            : ($this->side2_confirmed ?? []);
    }
}
