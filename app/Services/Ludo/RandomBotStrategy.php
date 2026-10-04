<?php

namespace App\Services\Ludo;

/**
 * Picks a uniformly random legal move. Good enough for filling tables
 * until Phase 5 builds the real bot economy.
 */
class RandomBotStrategy implements BotStrategy
{
    public function chooseMove(array $legalMoves, array $board, string $color, int $dice): int
    {
        return $legalMoves[array_rand($legalMoves)];
    }
}
