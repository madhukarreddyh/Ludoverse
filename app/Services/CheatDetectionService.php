<?php

namespace App\Services;

use App\Models\CheatLog;
use App\Models\LudoMatch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ludo\MatchService;

/**
 * Anti-cheat flagging. Every check LOGS (cheat_logs) and COUNTS flags in
 * a rolling window; reaching the threshold auto-suspends the account and
 * bans its device hashes. No single check bans instantly — flags are
 * evidence, suspension is the escalation.
 *
 * All thresholds live in settings and are commented there.
 */
class CheatDetectionService
{
    public function __construct(protected DeviceService $devices) {}

    /**
     * Record a flag. Returns the total flags in the window AFTER this one.
     * Auto-suspends at `cheat_auto_suspend_flags` within
     * `cheat_flag_window_hours`.
     */
    public function flag(User $user, string $type, ?LudoMatch $match = null, array $details = []): int
    {
        CheatLog::create([
            'user_id' => $user->id,
            'match_id' => $match?->id,
            'type' => $type,
            'details' => $details,
            'created_at' => now(),
        ]);

        $windowHours = (int) Setting::get('cheat_flag_window_hours', '24');
        $count = CheatLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(max(1, $windowHours)))
            ->count();

        $threshold = (int) Setting::get('cheat_auto_suspend_flags', '3');
        if ($count >= max(1, $threshold) && $user->status === 'active') {
            $user->forceFill(['status' => 'suspended'])->save();
            $this->devices->banUserDevices(
                $user->fresh(),
                "auto: {$count} cheat flags within {$windowHours}h"
            );
        }

        return $count;
    }

    /**
     * Score anomaly: a single-match score above the theoretical maximum
     * for the mode. Max = every token home + win bonus + the configured
     * upper bound on captures (captures are the only unbounded term, so
     * the bound is deliberately generous to avoid false positives).
     */
    public function theoreticalMaxScore(): int
    {
        $maxCaptures = (int) Setting::get('cheat_max_captures_per_match', '20');

        return 4 * MatchService::SCORE_HOME
            + MatchService::SCORE_WIN_BONUS
            + $maxCaptures * MatchService::SCORE_CAPTURE_ATTACKER;
    }

    /**
     * Called at match settlement. Flags (but does not ban) humans whose
     * score exceeds the theoretical maximum.
     */
    public function checkScoreAnomaly(LudoMatch $match): void
    {
        $max = $this->theoreticalMaxScore();

        foreach ($match->players()->where('is_bot', false)->whereNotNull('user_id')->get() as $player) {
            if ($player->score > $max && $player->user) {
                $this->flag($player->user, 'impossible_score', $match, [
                    'score' => $player->score,
                    'theoretical_max' => $max,
                    'mode' => $match->mode,
                ]);
            }
        }
    }

    /**
     * Client-integrity: the X-Client-Version header must match the
     * configured client_version. Mismatch is flagged, never an instant
     * ban — outdated clients are common, cheaters are rare.
     */
    public function checkClientVersion(User $user, ?string $headerValue): bool
    {
        $expected = (string) Setting::get('client_version', '1.0.0');

        if ($headerValue === null || $headerValue === '') {
            $this->flag($user, 'client_version_mismatch', null, [
                'expected' => $expected, 'received' => null,
            ]);

            return false;
        }

        if (! hash_equals($expected, $headerValue)) {
            $this->flag($user, 'client_version_mismatch', null, [
                'expected' => $expected, 'received' => $headerValue,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Impossible move timing: a move submitted faster than
     * cheat_min_move_ms after the dice is superhuman — log it.
     * Returns true when the timing looks impossible.
     */
    public function checkMoveTiming(User $user, LudoMatch $match): bool
    {
        $rolledAt = $match->dice_rolled_at;
        if (! $rolledAt) {
            return false;
        }

        $minMs = (int) Setting::get('cheat_min_move_ms', '200');
        $elapsedMs = (int) (now()->diffInMilliseconds($rolledAt));

        if ($elapsedMs < $minMs) {
            $this->flag($user, 'impossible_move_timing', $match, [
                'elapsed_ms' => $elapsedMs,
                'min_ms' => $minMs,
            ]);

            return true;
        }

        return false;
    }
}
