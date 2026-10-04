<?php

namespace Tests\Unit;

use App\Services\Ludo\EasyBotStrategy;
use App\Services\Ludo\HardBotStrategy;
use App\Services\Ludo\LudoEngine;
use PHPUnit\Framework\TestCase;

/**
 * Statistical skill check: Hard must beat Easy decisively over 30
 * fully deterministic (seeded) engine-vs-engine games.
 *
 * Difficulty is a statistical nudge, not a win-ratio guarantee — but a
 * "hard" strategy that can't beat random play most of the time is just
 * broken. Seeds are fixed, so this can never flake.
 */
class BotStrategySimulationTest extends TestCase
{
    private const GAMES = 30;

    private const FIRST_SEED = 1000;

    public function test_hard_beats_easy_statistically(): void
    {
        $hardWins = 0;
        for ($g = 0; $g < self::GAMES; $g++) {
            if ($this->playGame(self::FIRST_SEED + $g) === 'hard') {
                $hardWins++;
            }
        }

        $this->assertGreaterThan(
            self::GAMES * 0.6,
            $hardWins,
            "Hard won only {$hardWins}/".self::GAMES.' games vs Easy — the heuristic is not working.'
        );
    }

    /**
     * One simplified-rules 1v1: alternate single rolls (no extra turn on
     * 6 — both sides face identical rules, so move quality is what's
     * compared). Returns 'hard' (red) or 'easy' (green).
     */
    private function playGame(int $seed): string
    {
        // Deterministic: engine re-seeds mt_srand($seed); Easy's mt_rand
        // draws then follow a fixed sequence.
        mt_srand($seed);
        $engine = new LudoEngine($seed);
        $hard = new HardBotStrategy;
        $easy = new EasyBotStrategy;

        $board = ['red' => [-1, -1, -1, -1], 'green' => [-1, -1, -1, -1]];
        $strategies = ['red' => $hard, 'green' => $easy];

        for ($turn = 0; $turn < 4000; $turn++) {
            foreach (['red', 'green'] as $color) {
                $dice = $engine->rollDice();
                $legal = $engine->legalMoves($board, $color, $dice);
                if ($legal !== []) {
                    $token = $strategies[$color]->chooseMove($legal, $board, $color, $dice);
                    $board = $engine->applyMove($board, $color, $token, $dice)['board'];
                }
                if ($engine->isFinished($board, $color)) {
                    return $color === 'red' ? 'hard' : 'easy';
                }
            }
        }

        // Turn cap (practically unreachable): more finished tokens wins,
        // then total progress.
        $score = fn (string $c) => [
            count(array_filter($board[$c], fn ($s) => $s === 56)),
            array_sum(array_map(fn ($s) => max($s, 0), $board[$c])),
        ];

        return $score('red') <=> $score('green') >= 0 ? 'hard' : 'easy';
    }
}
