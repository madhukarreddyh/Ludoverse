<?php

namespace App\Services\Ludo;

/**
 * Thrown when a client asks for a move the rules do not allow.
 * MatchService converts this into a 422 ILLEGAL_MOVE with NO state change.
 */
class IllegalMoveException extends \RuntimeException
{
}

/**
 * Pure Ludo rules engine — no database, no framework, no I/O.
 *
 * Token position encoding (steps travelled from the colour's start square):
 *   -1  -> in base (off the board)
 *   0..50 -> on the main 52-square track
 *   51..55 -> the colour's private home stretch (5 squares)
 *   56  -> home (finished)
 *
 * A token's absolute track square is (COLOR_STARTS[color] + steps) % 52.
 * Captures can only happen on the main track (steps 0..50) and never on
 * safe squares. Leaving base requires an exact 6; entering home requires
 * an exact roll (steps + dice must be <= 56).
 *
 * The board is a plain array: ['red' => [t0, t1, t2, t3], 'green' => [...]].
 * Only colours present in the array are considered.
 */
class LudoEngine
{
    /**
     * Where each colour enters the shared 52-square track. Classic four
     * colours sit 13 apart; the extra team-mode colours are interleaved
     * so 6- and 8-player tables still space everyone out.
     */
    public const COLOR_STARTS = [
        'red' => 0,
        'green' => 13,
        'yellow' => 26,
        'blue' => 39,
        'orange' => 6,
        'purple' => 19,
        'teal' => 32,
        'pink' => 45,
    ];

    /**
     * Absolute track squares where captures are impossible (classic set).
     */
    public const SAFE_SQUARES = [0, 8, 13, 21, 26, 34, 39, 47];

    public const TOKENS_PER_PLAYER = 4;
    public const HOME_STEPS = 56;      // steps value of a finished token
    public const TRACK_END = 50;       // last steps value still on main track
    public const BASE = -1;

    private ?int $seed;

    /**
     * @param ?int $seed Deterministic RNG for tests. When null, uses
     *                   random_int() (cryptographically secure).
     */
    public function __construct(?int $seed = null)
    {
        $this->seed = $seed;
        if ($seed !== null) {
            mt_srand($seed);
        }
    }

    /**
     * Server-side dice — the client NEVER supplies this.
     */
    public function rollDice(): int
    {
        // mt_srand() only affects mt_rand(), never random_int(), so seeding
        // for tests cannot weaken production randomness elsewhere.
        return $this->seed === null ? random_int(1, 6) : mt_rand(1, 6);
    }

    /**
     * Absolute track square (0..51) for a token, or null when the token is
     * in base, in the home stretch, or already home.
     */
    public function absoluteSquare(string $color, int $steps): ?int
    {
        if ($steps < 0 || $steps > self::TRACK_END) {
            return null;
        }

        return (self::COLOR_STARTS[$color] + $steps) % 52;
    }

    /**
     * Token indexes (0..3) of $color that may legally move for $dice.
     */
    public function legalMoves(array $board, string $color, int $dice): array
    {
        $this->assertColor($board, $color);

        $legal = [];
        foreach ($board[$color] as $index => $steps) {
            if ($this->canMove($steps, $dice)) {
                $legal[] = $index;
            }
        }

        return $legal;
    }

    /**
     * Apply a move. Returns ['board' => $newBoard, 'events' => [...]].
     * Events: ['type' => 'move'|'capture'|'home'|'leave_base', ...].
     *
     * @throws IllegalMoveException when the move breaks the rules.
     */
    public function applyMove(array $board, string $color, int $tokenIndex, int $dice): array
    {
        $this->assertColor($board, $color);

        if ($tokenIndex < 0 || $tokenIndex >= self::TOKENS_PER_PLAYER) {
            throw new IllegalMoveException("Token index {$tokenIndex} out of range.");
        }
        if ($dice < 1 || $dice > 6) {
            throw new IllegalMoveException("Dice {$dice} out of range.");
        }

        $steps = $board[$color][$tokenIndex];

        if (! $this->canMove($steps, $dice)) {
            throw new IllegalMoveException(
                "Token {$tokenIndex} at {$steps} cannot move on dice {$dice}."
            );
        }

        $board[$color][$tokenIndex] = $steps === self::BASE ? 0 : $steps + $dice;
        $newSteps = $board[$color][$tokenIndex];

        $events = [];
        if ($steps === self::BASE) {
            $events[] = ['type' => 'leave_base', 'color' => $color, 'token' => $tokenIndex];
        } else {
            $events[] = [
                'type' => 'move', 'color' => $color, 'token' => $tokenIndex,
                'from' => $steps, 'to' => $newSteps,
            ];
        }

        // A token reaching the last step is home — worth points, game over
        // for that token.
        if ($newSteps === self::HOME_STEPS) {
            $events[] = ['type' => 'home', 'color' => $color, 'token' => $tokenIndex];
            return ['board' => $board, 'events' => $events];
        }

        // Captures: landing on an opponent's token on a non-safe main-track
        // square sends every opponent token there back to base.
        $square = $this->absoluteSquare($color, $newSteps);
        if ($square !== null && ! in_array($square, self::SAFE_SQUARES, true)) {
            foreach ($board as $otherColor => $tokens) {
                if ($otherColor === $color) {
                    continue;
                }
                foreach ($tokens as $otherIndex => $otherSteps) {
                    if ($this->absoluteSquare($otherColor, $otherSteps) === $square) {
                        $board[$otherColor][$otherIndex] = self::BASE;
                        $events[] = [
                            'type' => 'capture',
                            'attacker_color' => $color,
                            'attacker_token' => $tokenIndex,
                            'victim_color' => $otherColor,
                            'victim_token' => $otherIndex,
                        ];
                    }
                }
            }
        }

        return ['board' => $board, 'events' => $events];
    }

    /**
     * True when every token of $color is home.
     */
    public function isFinished(array $board, string $color): bool
    {
        $this->assertColor($board, $color);

        foreach ($board[$color] as $steps) {
            if ($steps !== self::HOME_STEPS) {
                return false;
            }
        }

        return true;
    }

    /**
     * A single steps value: can it move on $dice?
     * - Base (-1) needs exactly 6.
     * - Anything else needs steps + dice <= 56 (exact roll into home).
     * - Home (56) never moves.
     */
    private function canMove(int $steps, int $dice): bool
    {
        if ($steps === self::BASE) {
            return $dice === 6;
        }

        if ($steps >= self::HOME_STEPS) {
            return false;
        }

        return $steps + $dice <= self::HOME_STEPS;
    }

    private function assertColor(array $board, string $color): void
    {
        if (! array_key_exists($color, $board) || ! isset(self::COLOR_STARTS[$color])) {
            throw new IllegalMoveException("Unknown color '{$color}'.");
        }
    }
}
