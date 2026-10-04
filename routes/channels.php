<?php

use App\Models\MatchPlayer;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Only seated players of a match may listen to its private channel.
Broadcast::channel('match.{id}', function ($user, $id) {
    return MatchPlayer::where('match_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});
