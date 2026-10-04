<?php

namespace App\Events;

use App\Models\LudoMatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchFinished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels, BroadcastsOnMatchChannel;

    public function __construct(
        public LudoMatch $match,
        public ?int $winnerUserId,
        public ?int $winningTeam,
        public array $scores,
        public array $payouts,
    ) {
    }

    public function broadcastAs(): string
    {
        return 'match.finished';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->match->id,
            'winner_user_id' => $this->winnerUserId,
            'winning_team' => $this->winningTeam,
            'scores' => $this->scores,
            // [{user_id, amount_paise}] — what each winner was credited.
            'payouts' => $this->payouts,
        ];
    }
}
