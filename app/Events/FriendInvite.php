<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A friend invited you to a private 1v1 table. Broadcast on the
 * friend's private `user.{id}` channel (authorized in
 * routes/channels.php — only that user may listen).
 */
class FriendInvite implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $friendUserId,
        public int $matchId,
        public int $inviterUserId,
        public string $inviterName,
        public string $inviterGameId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->friendUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'friend.invite';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id' => $this->matchId,
            'inviter_user_id' => $this->inviterUserId,
            'inviter_name' => $this->inviterName,
            'inviter_game_id' => $this->inviterGameId,
        ];
    }
}
