<?php

namespace App\Services\Ludo;

/**
 * Decides which legal move a bot takes. Phase 5 may add smarter
 * strategies; the engine and MatchService only depend on this contract.
 */
interface BotStrategy
{
    /**
     * @param int[] $legalMoves token indexes the rules allow
     * @param array $board      current board (color => [4 token steps])
     * @param string $color     the bot's colour
     * @param int $dice         the server-rolled dice
     * @return int              the chosen token index
     */
    public function chooseMove(array $legalMoves, array $board, string $color, int $dice): int;
}
