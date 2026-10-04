<?php

namespace App\Events;

use App\Models\LudoMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiceRolled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsOnMatchChannel;

    public function __construct(
        public LudoMatch $match,
        public ?int $userId,
        public int $dice,
        public array $legalMoves,
        public bool $autoPassed = false,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'dice.rolled';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'user_id' => $this->userId,
            'dice' => $this->dice,
            'legal_moves' => $this->legalMoves,
            // True when the roll produced no legal move and the turn
            // auto-passed to the next player.
            'auto_passed' => $this->autoPassed,
        ];
    }
}
