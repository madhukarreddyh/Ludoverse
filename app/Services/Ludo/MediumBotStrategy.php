<?php

namespace App\Services\Ludo;

/**
 * Medium bot: simple one-ply heuristic.
 *   - captures first,
 *   - leaving base on a 6 is good,
 *   - reaching home is good,
 *   - avoids moving a safe token into danger,
 *   - prefers rescuing a token that is currently in danger,
 *   - slight preference for the furthest-advanced token (keeps the
 *     pack moving instead of shuffling one token back and forth).
 */
class MediumBotStrategy extends BaseBotStrategy
{
    public function chooseMove(array $legalMoves, array $board, string $color, int $dice): int
    {
        $best = $legalMoves[0];
        $bestScore = -INF;

        foreach ($legalMoves as $token) {
            $steps = $board[$color][$token];
            [$newBoard, $events] = $this->simulate($board, $color, $token, $dice);
            $newSteps = $newBoard[$color][$token];

            $score = 0.0;

            if ($this->captureCount($events) > 0) {
                $score += 100;
            }
            if ($steps === LudoEngine::BASE) {
                $score += 50; // leaving base on the 6
            }
            if ($newSteps === LudoEngine::HOME_STEPS) {
                $score += 80;
            }

            $wasDanger = $this->tokenInDanger($board, $color, $token);
            $willDanger = $this->tokenInDanger($newBoard, $color, $token);
            if ($willDanger && ! $wasDanger) {
                $score -= 60; // don't hang a safe token out to dry
            }
            if ($wasDanger && ! $willDanger) {
                $score += 40; // rescue
            }

            $score += $newSteps * 0.5; // keep the leaders moving

            // Deterministic tie-break: first best wins, so seeded runs
            // reproduce exactly.
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $token;
            }
        }

        return $best;
    }
}
