<?php

namespace App\Services;

use App\Models\AdminNotice;
use App\Models\FraudFlag;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

/**
 * Risk manager (`risk:scan`): watches the money flowing through the
 * platform once a day (or on demand) and reacts to two danger signs:
 *
 *  1. Withdrawal spike — withdrawals today exceed deposits x risk_wd_ratio:
 *     the house may be bleeding, so bot_difficulty is raised ONE level
 *     (easy -> medium -> hard, capped at hard) and an admin notice is
 *     created. This only shifts win RATES statistically over many games;
 *     it cannot target a per-game outcome (dice variance).
 *  2. High win rate — any player winning > 70% of their last 20 finished
 *     matches gets an OPEN high_winrate flag, so matchmaking can seat
 *     harder bots against them (see MatchService::fillWaitingMatchesWithBots).
 */
class RiskService
{
    public const DIFFICULTY_LADDER = ['easy', 'medium', 'hard'];

    /**
     * Today's money picture: deposits, withdrawals, win payouts,
     * commissions, and platform profit.
     */
    public function dailyNumbers(): array
    {
        $today = fn ($q) => $q->whereDate('created_at', today());

        $deposits = (int) $today(WalletLedger::where('transaction_type', 'deposit')->where('amount_paise', '>', 0))->sum('amount_paise');
        $withdrawals = (int) abs($today(WalletLedger::where('transaction_type', 'withdrawal')->where('amount_paise', '<', 0))->sum('amount_paise'));
        $winPayouts = (int) $today(WalletLedger::where('transaction_type', 'win')->where('amount_paise', '>', 0))->sum('amount_paise');
        $commissions = (int) $today(WalletLedger::where('transaction_type', 'commission')->where('amount_paise', '>', 0))->sum('amount_paise');

        return [
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'win_payouts' => $winPayouts,
            'commissions' => $commissions,
            // Profit = money in − money out. Win payouts leave the platform;
            // commissions come back in.
            'profit' => $deposits - $withdrawals - $winPayouts + $commissions,
        ];
    }

    /**
     * Run the full scan. Returns a summary of what happened.
     */
    public function scan(): array
    {
        $numbers = $this->dailyNumbers();
        $result = ['numbers' => $numbers, 'bot_difficulty_raised' => false, 'high_winrate_flags' => 0];

        // --- Withdrawal spike -------------------------------------------
        $ratio = (float) Setting::get('risk_wd_ratio', '0.9');
        if ($numbers['deposits'] > 0 && $numbers['withdrawals'] > $numbers['deposits'] * $ratio) {
            $raised = $this->raiseBotDifficulty();
            $result['bot_difficulty_raised'] = $raised;

            AdminNotice::create([
                'type' => 'risk_withdrawal_spike',
                'title' => 'Withdrawal spike detected',
                'body' => sprintf(
                    'Withdrawals today (₹%.2f) exceeded %.0f%% of deposits (₹%.2f). Bot difficulty %s.',
                    $numbers['withdrawals'] / 100, $ratio * 100, $numbers['deposits'] / 100,
                    $raised ? 'raised one level' : 'already at hard — no change'
                ),
            ]);
        }

        // --- High win-rate players ---------------------------------------
        $result['high_winrate_flags'] = $this->scanHighWinRates();

        return $result;
    }

    /**
     * Raise bot_difficulty one rung; returns true when it changed.
     */
    public function raiseBotDifficulty(): bool
    {
        $current = (string) Setting::get('bot_difficulty', 'medium');
        $pos = array_search($current, self::DIFFICULTY_LADDER, true);
        if ($pos === false) {
            $pos = 1;
        }
        if ($pos >= count(self::DIFFICULTY_LADDER) - 1) {
            return false; // already at hard — the cap
        }

        Setting::set('bot_difficulty', self::DIFFICULTY_LADDER[$pos + 1]);

        return true;
    }

    /**
     * Flag players winning > 70% of their last 20 finished matches
     * (minimum 5 games, so a 1-0 newcomer is never flagged).
     */
    protected function scanHighWinRates(): int
    {
        $created = 0;

        $userIds = MatchPlayer::where('is_bot', false)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $seats = MatchPlayer::where('user_id', $userId)
                ->where('is_bot', false)
                ->whereHas('match', fn ($q) => $q->where('status', 'finished'))
                ->orderByDesc('id')
                ->limit(20)
                ->with('match')
                ->get();

            if ($seats->count() < 5) {
                continue;
            }

            $wins = 0;
            foreach ($seats as $seat) {
                $match = $seat->match;
                if ($this->seatWon($match, $seat)) {
                    $wins++;
                }
            }

            if ($wins / $seats->count() > 0.7) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                $signature = 'winrate:'.date('Y-m-d').':'.$userId;
                $exists = FraudFlag::where('user_id', $userId)
                    ->where('type', 'high_winrate')
                    ->whereIn('status', ['open', 'confirmed'])
                    ->where('details->signature', $signature)
                    ->exists();
                if (! $exists) {
                    FraudFlag::create([
                        'user_id' => $userId,
                        'type' => 'high_winrate',
                        'details' => [
                            'signature' => $signature,
                            'wins' => $wins,
                            'games' => $seats->count(),
                            'win_rate' => round($wins / $seats->count(), 3),
                        ],
                        'status' => 'open',
                        'created_at' => now(),
                    ]);
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Did this seat win its match? 1v1: winner_user_id. Team modes: the
     * seat's team is the winning team and the seat wasn't abandoned.
     */
    protected function seatWon(LudoMatch $match, MatchPlayer $seat): bool
    {
        if ($match->mode === '1v1') {
            return (int) $match->winner_user_id === (int) $seat->user_id;
        }

        return $match->winning_team !== null
            && (int) $match->winning_team === (int) $seat->team
            && in_array($seat->status, ['playing', 'finished'], true);
    }
}
