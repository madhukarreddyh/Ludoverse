<?php

namespace App\Services\Ludo;

/**
 * Hard bot: full heuristic, still one ply (no minimax — the dice make
 * deep search pointless and the turn budget is 30 seconds server-side).
 *
 * Priority order, by weight:
 *   1. Capture — and among captures, take the most advanced victim
 *      ("target the weakest opponent": the token closest to home).
 *   2. Danger — never hang a token (heavy penalty for moving INTO
 *      danger, big reward for escaping it).
 *   3. Race to home — reaching home, then advancing inside the safe
 *      home stretch, then raw progress.
 *   4. Leaving base on a 6.
 *
 * HONESTY NOTE (bot economy): these weights shift WIN RATES
 * statistically — over many games Hard beats Easy far more often than
 * not. But no weight set can target an exact win ratio (say "win 55%
 * of games"): dice variance dominates any single game, so difficulty
 * is a statistical nudge, approximate and never deterministic.
 */
class HardBotStrategy extends BaseBotStrategy
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

            // 1. Captures dominate: each capture plus a bonus scaled to
            // how far along the victim was.
            $captures = $this->captureCount($events);
            if ($captures > 0) {
                $score += 1000 * $captures + 5 * $this->maxVictimSteps($board, $events);
            }

            // 2. Danger handling.
            $wasDanger = $this->tokenInDanger($board, $color, $token);
            $willDanger = $this->tokenInDanger($newBoard, $color, $token);
            if ($willDanger && ! $wasDanger) {
                $score -= 600;
            } elseif ($wasDanger && ! $willDanger) {
                $score += 400;
            } elseif ($willDanger) {
                $score -= 100; // stayed exposed — better than nothing
            } else {
                $score += 20; // safe and staying safe
            }

            // 3. Race to home.
            if ($newSteps === LudoEngine::HOME_STEPS) {
                $score += 800;
            } elseif ($newSteps > LudoEngine::TRACK_END) {
                // Inside the safe home stretch: push the furthest one.
                $score += ($newSteps - LudoEngine::TRACK_END) * 30;
            }
            $score += $newSteps * 1.0;

            // 4. Leaving base is always progress (the start square is safe).
            if ($steps === LudoEngine::BASE) {
                $score += 150;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $token;
            }
        }

        return $best;
    }
}
