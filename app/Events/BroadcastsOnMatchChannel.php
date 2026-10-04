<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

/**
 * Shared channel helper: every match event goes to private `match.{id}`,
 * authorized in routes/channels.php (players of that match only).
 */
trait BroadcastsOnMatchChannel
{
    public function broadcastOn(): array
    {
        return [new PrivateChannel("match.{$this->match->id}")];
    }
}
