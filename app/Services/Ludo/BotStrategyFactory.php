<?php

namespace App\Services\Ludo;

use App\Models\Setting;

/**
 * Resolves which BotStrategy a seated bot plays with.
 *
 * Per-bot difficulty comes from match_players.bot_difficulty (set when
 * the bot is seated by auto-fill); when null it falls back to the
 * admin's `bot_difficulty` setting (easy/medium/hard, default medium).
 *
 * HONESTY NOTE: difficulty only shifts win RATES statistically. A
 * "hard" bot does not win a fixed share of games — dice variance
 * dominates single games, so exact win-ratio targeting is approximate,
 * never deterministic. Do not promise players (or admins) a precise
 * bot win percentage.
 */
class BotStrategyFactory
{
    public function forDifficulty(?string $difficulty): BotStrategy
    {
        $difficulty ??= Setting::get('bot_difficulty', 'medium');

        return match (strtolower((string) $difficulty)) {
            'easy' => new EasyBotStrategy,
            'hard' => new HardBotStrategy,
            // Unknown values degrade to medium rather than crashing a
            // live match on a typo'd setting.
            default => new MediumBotStrategy,
        };
    }

    /**
     * The configured default difficulty (for admin display / tests).
     */
    public function defaultDifficulty(): string
    {
        $d = strtolower((string) Setting::get('bot_difficulty', 'medium'));

        return in_array($d, ['easy', 'medium', 'hard'], true) ? $d : 'medium';
    }
}
