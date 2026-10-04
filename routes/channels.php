<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private per-user channel: friend invites, account notices, ...
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Match channel: any authenticated user may LISTEN (spectators included —
// they receive MoveApplied etc. live). Acting (roll/move) is still gated
// by PlayController::authorizeSeat, which 403s non-seated users.
Broadcast::channel('match.{id}', function ($user, $id) {
    return $user !== null;
});
