<?php

namespace App\Events;

use App\Models\LudoMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TurnMissed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsOnMatchChannel;

    public function __construct(
        public LudoMatch $match,
        public ?int $userId,
        public int $missedTurns,
        public string $reason,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'turn.missed';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'user_id' => $this->userId,
            // 'timeout' (30s deadline passed) or 'forfeit' (three sixes).
            'reason' => $this->reason,
            'missed_turns' => $this->missedTurns,
            'next_turn_user_id' => $this->match->current_turn_user_id,
        ];
    }
}
