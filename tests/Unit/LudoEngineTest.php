<?php

namespace Tests\Unit;

use App\Services\Ludo\IllegalMoveException;
use App\Services\Ludo\LudoEngine;
use PHPUnit\Framework\TestCase;

/**
 * Pure engine tests — no database, no framework. The engine is the only
 * place dice and move legality exist, so these pin the rules down hard.
 */
class LudoEngineTest extends TestCase
{
    private function board(array $red = [-1, -1, -1, -1], array $green = [-1, -1, -1, -1]): array
    {
        return ['red' => $red, 'green' => $green];
    }

    public function test_dice_distribution_is_sane(): void
    {
        $engine = new LudoEngine(); // unseeded: real random_int()
        $counts = array_fill(1, 6, 0);

        for ($i = 0; $i < 6000; $i++) {
            $counts[$engine->rollDice()]++;
        }

        // Each face should land within 13–20% (uniform is 16.67%).
        foreach ($counts as $face => $count) {
            $this->assertGreaterThanOrEqual(780, $count, "Face {$face} under-rolled.");
            $this->assertLessThanOrEqual(1200, $count, "Face {$face} over-rolled.");
        }
    }

    public function test_seeded_engine_is_deterministic(): void
    {
        // NOTE: seeded engines share PHP's global mt stream, so the two
        // sequences must be drawn one-after-the-other, not interleaved.
        $seqA = [];
        $a = new LudoEngine(12345);
        for ($i = 0; $i < 50; $i++) {
            $seqA[] = $a->rollDice();
        }

        $seqB = [];
        $b = new LudoEngine(12345);
        for ($i = 0; $i < 50; $i++) {
            $seqB[] = $b->rollDice();
        }

        $this->assertSame($seqA, $seqB);
    }

    public function test_token_leaves_base_only_on_six(): void
    {
        $engine = new LudoEngine();

        $this->assertSame([], $engine->legalMoves($this->board(), 'red', 1));
        $this->assertSame([], $engine->legalMoves($this->board(), 'red', 5));
        $this->assertSame([0, 1, 2, 3], $engine->legalMoves($this->board(), 'red', 6));
    }

    public function test_finished_tokens_have_no_moves(): void
    {
        $engine = new LudoEngine();
        $board = $this->board([56, 56, 56, 56]);

        foreach ([1, 2, 3, 4, 5, 6] as $dice) {
            $this->assertSame([], $engine->legalMoves($board, 'red', $dice), "dice {$dice}");
        }
    }

    public function test_exact_roll_needed_to_enter_home(): void
    {
        $engine = new LudoEngine();

        // 54 + 2 = 56 -> home.
        $this->assertSame([0], $engine->legalMoves($this->board([54, -1, -1, -1]), 'red', 2));
        $result = $engine->applyMove($this->board([54, -1, -1, -1]), 'red', 0, 2);
        $this->assertSame(56, $result['board']['red'][0]);
        $this->assertContains('home', array_column($result['events'], 'type'));

        // 54 + 3 = 57 -> overshoot, illegal.
        $this->assertSame([], $engine->legalMoves($this->board([54, -1, -1, -1]), 'red', 3));
        $this->expectException(IllegalMoveException::class);
        $engine->applyMove($this->board([54, -1, -1, -1]), 'red', 0, 3);
    }

    public function test_move_from_base_lands_on_start_square(): void
    {
        $engine = new LudoEngine();
        $result = $engine->applyMove($this->board(), 'red', 2, 6);

        $this->assertSame(0, $result['board']['red'][2]);
        $this->assertSame('leave_base', $result['events'][0]['type']);
    }

    public function test_capture_sends_victim_to_base(): void
    {
        $engine = new LudoEngine();
        // Red moves 9 -> 10 (abs 10). Green at steps 49 -> abs (13+49)%52 = 10.
        // Square 10 is not safe -> capture.
        $board = $this->board([9, -1, -1, -1], [49, -1, -1, -1]);

        $result = $engine->applyMove($board, 'red', 0, 1);

        $this->assertSame(10, $result['board']['red'][0]);
        $this->assertSame(-1, $result['board']['green'][0], 'Victim must return to base.');
        $captures = array_filter($result['events'], fn ($e) => $e['type'] === 'capture');
        $this->assertCount(1, $captures);
        $capture = array_values($captures)[0];
        $this->assertSame('red', $capture['attacker_color']);
        $this->assertSame('green', $capture['victim_color']);
    }

    public function test_safe_squares_block_capture(): void
    {
        $engine = new LudoEngine();
        foreach (LudoEngine::SAFE_SQUARES as $safe) {
            // Red token one step before the safe square.
            $redSteps = ($safe - 0 + 52) % 52; // absolute == steps for red
            $redSteps = $redSteps === 0 ? 52 : $redSteps; // keep on track
            if ($redSteps > 50) {
                continue;
            }
            $greenSteps = ($safe - 13 + 52) % 52;

            $board = $this->board([$redSteps - 1, -1, -1, -1], [$greenSteps, -1, -1, -1]);
            $result = $engine->applyMove($board, 'red', 0, 1);

            $this->assertSame(
                $greenSteps, $result['board']['green'][0],
                "Green must survive on safe square {$safe}."
            );
            $captures = array_filter($result['events'], fn ($e) => $e['type'] === 'capture');
            $this->assertCount(0, $captures, "No capture allowed on safe square {$safe}.");
        }
    }

    public function test_start_square_is_safe_for_base_exit(): void
    {
        $engine = new LudoEngine();
        // Green sits on absolute 0 (red's start): (13+g)%52 = 0 -> g = 39.
        $board = $this->board([-1, -1, -1, -1], [39, -1, -1, -1]);

        $result = $engine->applyMove($board, 'red', 0, 6);

        $this->assertSame(0, $result['board']['red'][0]);
        $this->assertSame(39, $result['board']['green'][0], 'Start square is safe.');
    }

    public function test_own_tokens_stack_without_capture(): void
    {
        $engine = new LudoEngine();
        $board = $this->board([10, 9, -1, -1]);

        $result = $engine->applyMove($board, 'red', 1, 1);

        $this->assertSame(10, $result['board']['red'][0]);
        $this->assertSame(10, $result['board']['red'][1]);
        $captures = array_filter($result['events'], fn ($e) => $e['type'] === 'capture');
        $this->assertCount(0, $captures);
    }

    public function test_illegal_moves_throw(): void
    {
        $engine = new LudoEngine();
        $board = $this->board([10, -1, -1, -1]);

        // Token in base on a non-6.
        try {
            $engine->applyMove($board, 'red', 1, 4);
            $this->fail('Expected IllegalMoveException.');
        } catch (IllegalMoveException) {
        }

        // Token index out of range.
        try {
            $engine->applyMove($board, 'red', 4, 6);
            $this->fail('Expected IllegalMoveException.');
        } catch (IllegalMoveException) {
        }

        // Unknown colour.
        try {
            $engine->applyMove($board, 'pink', 0, 6);
            $this->fail('Expected IllegalMoveException.');
        } catch (IllegalMoveException) {
        }

        // Board must be untouched by failed attempts (applyMove copies).
        $this->assertSame([10, -1, -1, -1], $board['red']);
    }

    public function test_is_finished_only_when_all_four_home(): void
    {
        $engine = new LudoEngine();

        $this->assertTrue($engine->isFinished($this->board([56, 56, 56, 56]), 'red'));
        $this->assertFalse($engine->isFinished($this->board([56, 56, 56, 55]), 'red'));
        $this->assertFalse($engine->isFinished($this->board(), 'red'));
    }

    /**
     * A whole 1v1 game, both sides greedy, deterministic seed.
     * Proves the engine can actually reach a finished game.
     */
    public function test_full_1v1_game_completes_with_deterministic_seed(): void
    {
        $engine = new LudoEngine(987654321);
        $board = $this->board();

        $winner = null;
        $turns = 0;
        $colors = ['red', 'green'];
        $turn = 0;

        while ($turns < 20000) {
            $turns++;
            $color = $colors[$turn % 2];
            $dice = $engine->rollDice();
            $legal = $engine->legalMoves($board, $color, $dice);

            if ($legal === []) {
                $turn++;
                continue;
            }

            // Greedy: home entry > capture > leave base > furthest token.
            $choice = $this->greedyChoice($engine, $board, $color, $legal, $dice);
            $result = $engine->applyMove($board, $color, $choice, $dice);
            $board = $result['board'];

            if ($engine->isFinished($board, $color)) {
                $winner = $color;
                break;
            }

            // A 6 earns another turn (unless three sixes — simplified here
            // to a pass, the service layer enforces the real forfeit rule).
            if ($dice !== 6) {
                $turn++;
            }
        }

        $this->assertNotNull($winner, "Game did not finish in {$turns} turns.");
        $this->assertSame([56, 56, 56, 56], $board[$winner]);
        // Sanity: no token ever left the valid range.
        foreach ($board as $tokens) {
            foreach ($tokens as $steps) {
                $this->assertTrue($steps === -1 || ($steps >= 0 && $steps <= 56));
            }
        }
    }

    /**
     * Greedy bot for the full-game test: prefers finishing a token, then
     * capturing, then leaving base, then the furthest-advanced token.
     */
    private function greedyChoice(LudoEngine $engine, array $board, string $color, array $legal, int $dice): int
    {
        $tokens = $board[$color];
        $best = $legal[0];
        $bestRank = -1;

        foreach ($legal as $index) {
            $from = $tokens[$index];
            $to = $from === -1 ? 0 : $from + $dice;

            $rank = $to; // furthest token by default
            if ($to === 56) {
                $rank = 1000; // home entry wins
            } else {
                $square = $engine->absoluteSquare($color, $to);
                if ($square !== null && ! in_array($square, LudoEngine::SAFE_SQUARES, true)) {
                    foreach ($board as $otherColor => $otherTokens) {
                        if ($otherColor === $color) {
                            continue;
                        }
                        foreach ($otherTokens as $otherSteps) {
                            if ($engine->absoluteSquare($otherColor, $otherSteps) === $square) {
                                $rank = 500; // capture
                                break 2;
                            }
                        }
                    }
                }
                if ($from === -1) {
                    $rank = 100; // leave base
                }
            }

            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $index;
            }
        }

        return $best;
    }
}
