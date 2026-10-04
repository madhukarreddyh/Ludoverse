<?php

namespace App\Services\Ludo;

/**
 * Shared lookahead helpers for the heuristic bot strategies.
 *
 * All evaluation works on COPIES of the board: each candidate move is
 * simulated through LudoEngine::applyMove() (which is pure), so capture
 * detection and danger evaluation see the board exactly as it would look
 * after the move — including victims already removed by the capture.
 */
abstract class BaseBotStrategy implements BotStrategy
{
    protected LudoEngine $engine;

    public function __construct()
    {
        // No seed: the engine is only used for pure rule helpers here.
        $this->engine = new LudoEngine;
    }

    /**
     * Simulate a candidate move; returns [newBoard, events].
     * $token is guaranteed legal by the caller.
     */
    protected function simulate(array $board, string $color, int $token, int $dice): array
    {
        $result = $this->engine->applyMove($board, $color, $token, $dice);

        return [$result['board'], $result['events']];
    }

    /**
     * Number of opponent tokens this move would capture.
     */
    protected function captureCount(array $events): int
    {
        $n = 0;
        foreach ($events as $event) {
            if ($event['type'] === 'capture') {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Steps value of the most advanced victim (for "target the weakest
     * opponent" — the opponent closest to home hurts most to lose).
     */
    protected function maxVictimSteps(array $board, array $events): int
    {
        $max = 0;
        foreach ($events as $event) {
            if ($event['type'] === 'capture') {
                // $board is the PRE-move board: victim steps are still there.
                $steps = $board[$event['victim_color']][$event['victim_token']] ?? 0;
                $max = max($max, $steps);
            }
        }

        return $max;
    }

    /**
     * True when the token sits on the main track, off any safe square,
     * with an opponent 1..6 squares behind it (i.e. capturable next roll).
     */
    protected function tokenInDanger(array $board, string $color, int $token): bool
    {
        $steps = $board[$color][$token];

        // Base, home stretch and home are never capturable.
        if ($steps < 0 || $steps > LudoEngine::TRACK_END) {
            return false;
        }

        $square = $this->engine->absoluteSquare($color, $steps);
        if ($square === null || in_array($square, LudoEngine::SAFE_SQUARES, true)) {
            return false;
        }

        foreach ($board as $otherColor => $tokens) {
            if ($otherColor === $color) {
                continue;
            }
            foreach ($tokens as $otherSteps) {
                $otherSquare = $this->engine->absoluteSquare($otherColor, $otherSteps);
                if ($otherSquare === null) {
                    continue;
                }
                // Squares BEHIND us, modulo the 52-track.
                $behind = ($square - $otherSquare + 52) % 52;
                if ($behind >= 1 && $behind <= 6) {
                    return true;
                }
            }
        }

        return false;
    }
}
