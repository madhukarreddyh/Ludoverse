<?php

namespace Tests\Unit;

use App\Services\Ludo\BotStrategy;
use App\Services\Ludo\EasyBotStrategy;
use App\Services\Ludo\HardBotStrategy;
use App\Services\Ludo\LudoEngine;
use App\Services\Ludo\MediumBotStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: every strategy must ALWAYS return a legal move, over a
 * wide range of seeded board states (2-player and 4-player tables,
 * tokens everywhere from base to home).
 */
class BotStrategyTest extends TestCase
{
    /**
     * @return array<string, BotStrategy>
     */
    private function strategies(): array
    {
        return [
            'easy' => new EasyBotStrategy,
            'medium' => new MediumBotStrategy,
            'hard' => new HardBotStrategy,
        ];
    }

    #[DataProvider('strategyProvider')]
    public function test_always_returns_a_legal_move(string $name, BotStrategy $strategy): void
    {
        $engine = new LudoEngine;

        for ($seed = 1; $seed <= 120; $seed++) {
            mt_srand($seed * 7919 + 13);

            // Random 2- or 4-colour board, tokens anywhere plausible.
            $colors = ['red', 'green', 'yellow', 'blue'];
            $nColors = $seed % 2 === 0 ? 2 : 4;
            $board = [];
            for ($c = 0; $c < $nColors; $c++) {
                $tokens = [];
                for ($t = 0; $t < 4; $t++) {
                    $r = mt_rand(0, 9);
                    $tokens[] = match (true) {
                        $r <= 2 => -1,            // base
                        $r <= 7 => mt_rand(0, 55), // track / home stretch
                        default => 56,            // home
                    };
                }
                $board[$colors[$c]] = $tokens;
            }

            $color = $colors[mt_rand(0, $nColors - 1)];
            $dice = mt_rand(1, 6);
            $legal = $engine->legalMoves($board, $color, $dice);

            if ($legal === []) {
                continue; // nothing to choose — engine auto-passes
            }

            $choice = $strategy->chooseMove($legal, $board, $color, $dice);

            $this->assertContains(
                $choice, $legal,
                "{$name} chose illegal token {$choice} (seed {$seed}, color {$color}, dice {$dice})"
            );
            // And the engine itself must accept it (belt and braces).
            $engine->applyMove($board, $color, $choice, $dice);
        }
    }

    public static function strategyProvider(): array
    {
        return [
            'easy' => ['easy', new EasyBotStrategy],
            'medium' => ['medium', new MediumBotStrategy],
            'hard' => ['hard', new HardBotStrategy],
        ];
    }

    public function test_medium_prefers_capture_over_random_progress(): void
    {
        // Deterministic micro-scenario: red token 0 lands on green and
        // captures; token 1 just advances. Both strategies must capture.
        //
        // Red token0 at steps 6, dice 3 -> steps 9 -> abs square 9
        // (not a safe square). Green token0 at steps 48 -> abs
        // (13 + 48) % 52 = 9. Same non-safe square -> capture.
        $engine = new LudoEngine;
        $board = [
            'red' => [6, 20, -1, -1],
            'green' => [48, -1, -1, -1],
        ];
        $legal = $engine->legalMoves($board, 'red', 3);
        $this->assertEqualsCanonicalizing([0, 1], $legal);

        $events = $engine->applyMove($board, 'red', 0, 3)['events'];
        $this->assertSame('capture', $events[1]['type']);

        $medium = (new MediumBotStrategy)->chooseMove($legal, $board, 'red', 3);
        $this->assertSame(0, $medium, 'Medium should capture instead of advancing token 1');

        $hard = (new HardBotStrategy)->chooseMove($legal, $board, 'red', 3);
        $this->assertSame(0, $hard, 'Hard should capture instead of advancing token 1');
    }
}
