<?php

namespace App\Services\Ludo;

/**
 * Easy bot: picks a uniformly random legal move.
 *
 * Uses mt_rand() (not array_rand()) so seeded test runs are fully
 * deterministic via mt_srand().
 */
class EasyBotStrategy extends BaseBotStrategy
{
    public function chooseMove(array $legalMoves, array $board, string $color, int $dice): int
    {
        return $legalMoves[mt_rand(0, count($legalMoves) - 1)];
    }
}
