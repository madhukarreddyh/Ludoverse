<?php

namespace App\Observers;

use App\Models\User;

/**
 * Assigns the public friend code ("LV" + zero-padded id) right after the
 * row exists, so the id is known. Existing rows are backfilled by the
 * migration; the unique index guarantees no duplicates.
 */
class UserObserver
{
    public function created(User $user): void
    {
        if ($user->game_id) {
            return;
        }

        $user->forceFill([
            'game_id' => 'LV'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
        ])->saveQuietly();
    }
}
