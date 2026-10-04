<?php

namespace App\Events;

use App\Models\LudoMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MoveApplied implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsOnMatchChannel;

    public function __construct(
        public LudoMatch $match,
        public ?int $userId,
        public int $tokenIndex,
        public int $dice,
        public array $events,
        public array $scores,
        public ?int $nextTurnUserId,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'move.applied';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'user_id' => $this->userId,
            'token_index' => $this->tokenIndex,
            'dice' => $this->dice,
            'events' => $this->events,
            'scores' => $this->scores,
            'next_turn_user_id' => $this->nextTurnUserId,
            'board' => $this->match->board_state,
        ];
    }
}
