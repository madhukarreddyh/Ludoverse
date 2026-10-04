<?php

namespace App\Events;

use App\Models\LudoMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsOnMatchChannel;

    public function __construct(public LudoMatch $match, public array $players)
    {
    }

    public function broadcastAs(): string
    {
        return 'match.started';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'mode' => $this->match->mode,
            'bet_paise' => $this->match->bet_paise,
            'ends_at' => $this->match->ends_at?->toIso8601String(),
            'players' => $this->players,
            'current_turn_user_id' => $this->match->current_turn_user_id,
        ];
    }
}
