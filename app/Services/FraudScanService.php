<?php

namespace App\Services;

use App\Models\FraudFlag;
use App\Models\LudoMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Anti-collusion scanner (driven by the `fraud:scan` artisan command).
 *
 * Checks:
 *  1. same_ip_match     — two humans from one IP seated at one table
 *  2. same_device_match — two humans on one device hash at one table
 *  3. repeated_opponent — same 1v1 pair in > 5 matches / 24h
 *  4. intentional_loss  — a player loses > 80% vs the same opponent (min 5)
 *
 * Every hit creates an OPEN flag (details carry a stable signature so
 * re-scans never duplicate). Admins confirm (freeze wallet / suspend) or
 * dismiss at /hmkr/fraud. Matchmaking refuses to seat two users who share
 * an open same_ip/same_device flag (see MatchService).
 */
class FraudScanService
{
    /**
     * Run every check. Returns the number of NEW flags created.
     */
    public function scan(): int
    {
        $created = 0;
        $created += $this->scanSharedIpOrDevice('ip_address', 'same_ip_match');
        $created += $this->scanSharedIpOrDevice('device_hash', 'same_device_match');
        $created += $this->scanRepeatedOpponents();
        $created += $this->scanIntentionalLoss();

        return $created;
    }

    /**
     * One flag per (user, other_user, match) triple.
     */
    protected function flagOnce(User $user, string $type, string $signature, array $details): bool
    {
        $exists = FraudFlag::where('user_id', $user->id)
            ->where('type', $type)
            ->whereIn('status', ['open', 'confirmed'])
            ->where('details->signature', $signature)
            ->exists();

        if ($exists) {
            return false;
        }

        FraudFlag::create([
            'user_id' => $user->id,
            'type' => $type,
            'details' => array_merge($details, ['signature' => $signature]),
            'status' => 'open',
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Checks 1 & 2: humans sharing an IP / device hash at the same table
     * in the last 24h. Bots are excluded (they share the server's network).
     */
    protected function scanSharedIpOrDevice(string $column, string $type): int
    {
        $created = 0;

        $rows = MatchPlayer::query()
            ->select('match_id', $column)
            ->selectRaw('GROUP_CONCAT(DISTINCT user_id) AS user_ids')
            ->where('is_bot', false)
            ->whereNotNull('user_id')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->whereHas('match', fn ($q) => $q->where('created_at', '>=', now()->subDay()))
            ->groupBy('match_id', $column)
            ->havingRaw('COUNT(DISTINCT user_id) > 1')
            ->get();

        foreach ($rows as $row) {
            $userIds = array_map('intval', explode(',', (string) $row->user_ids));
            sort($userIds);
            $value = (string) $row->{$column};

            foreach ($userIds as $userId) {
                $others = array_values(array_diff($userIds, [$userId]));
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                $signature = "match:{$row->match_id}:{$column}:{$value}:user:{$userId}";
                if ($this->flagOnce($user, $type, $signature, [
                    'match_id' => $row->match_id,
                    $column === 'ip_address' ? 'ip' : 'device_hash' => $value,
                    'other_user_ids' => $others,
                ])) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Check 3: the same 1v1 pair seated together in > 5 matches / 24h.
     */
    protected function scanRepeatedOpponents(): int
    {
        $created = 0;
        $threshold = 5;

        $pairs = DB::table('match_players as p1')
            ->join('match_players as p2', function ($join) {
                $join->on('p1.match_id', '=', 'p2.match_id')
                    ->whereRaw('p1.user_id < p2.user_id');
            })
            ->join('matches as m', 'm.id', '=', 'p1.match_id')
            ->where('m.mode', '1v1')
            ->where('m.status', 'finished')
            ->where('m.created_at', '>=', now()->subDay())
            ->where('p1.is_bot', false)->whereNotNull('p1.user_id')
            ->where('p2.is_bot', false)->whereNotNull('p2.user_id')
            ->groupBy('p1.user_id', 'p2.user_id')
            ->havingRaw('COUNT(*) > ?', [$threshold])
            ->select('p1.user_id as a', 'p2.user_id as b')
            ->selectRaw('COUNT(*) AS games')
            ->get();

        foreach ($pairs as $pair) {
            foreach ([(int) $pair->a, (int) $pair->b] as $userId) {
                $user = User::find($userId);
                if (! $user) {
                    continue;
                }
                $other = $userId === (int) $pair->a ? (int) $pair->b : (int) $pair->a;
                $signature = "pair24h:{$pair->a}:{$pair->b}";
                if ($this->flagOnce($user, 'repeated_opponent', $signature, [
                    'other_user_id' => $other,
                    'matches_24h' => (int) $pair->games,
                ])) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Check 4: in 1v1 over the last 30 days, a player who faced the same
     * opponent in >= 5 games and lost > 80% of them looks like they are
     * throwing games (chip dumping to the partner account).
     */
    protected function scanIntentionalLoss(): int
    {
        $created = 0;
        $minGames = 5;
        $lossRate = 0.8;

        $matches = LudoMatch::where('mode', '1v1')
            ->where('status', 'finished')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('winner_user_id')
            ->with(['players' => fn ($q) => $q->where('is_bot', false)->whereNotNull('user_id')])
            ->get();

        // stats[loserId][winnerId] = ['games' => n, 'losses' => n]
        $stats = [];
        foreach ($matches as $match) {
            $humans = $match->players->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            if (count($humans) !== 2) {
                continue;
            }
            $winner = (int) $match->winner_user_id;
            if (! in_array($winner, $humans, true)) {
                continue;
            }
            $loser = $humans[0] === $winner ? $humans[1] : $humans[0];
            $stats[$loser][$winner]['games'] = ($stats[$loser][$winner]['games'] ?? 0) + 1;
            $stats[$loser][$winner]['losses'] = ($stats[$loser][$winner]['losses'] ?? 0) + 1;
        }

        foreach ($stats as $loserId => $opponents) {
            foreach ($opponents as $winnerId => $row) {
                if ($row['games'] < $minGames) {
                    continue;
                }
                if ($row['losses'] / $row['games'] <= $lossRate) {
                    continue;
                }
                $user = User::find($loserId);
                if (! $user) {
                    continue;
                }
                $signature = "loss30d:{$loserId}:{$winnerId}";
                if ($this->flagOnce($user, 'intentional_loss', $signature, [
                    'other_user_id' => $winnerId,
                    'games' => $row['games'],
                    'losses' => $row['losses'],
                    'loss_rate' => round($row['losses'] / $row['games'], 3),
                ])) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Record a VPN/proxy suspicion. Flag-only, NEVER a block — see
     * HeuristicVpnCheck for why these signals are weak.
     */
    public function flagVpnSuspect(User $user, array $reasons, Request $request): bool
    {
        $signature = 'vpn:'.date('Y-m-d').':'.$user->id;

        return $this->flagOnce($user, 'vpn_suspect', $signature, [
            'reasons' => $reasons,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Matchmaking guard: do these two users share an OPEN same-IP or
     * same-device flag naming each other? If so they must never be
     * seated at the same table.
     */
    public function pairBlocked(int $userA, int $userB): bool
    {
        $blockedTypes = ['same_ip_match', 'same_device_match'];

        return FraudFlag::where(function ($q) use ($userA, $userB, $blockedTypes) {
            $q->where(function ($q) use ($userA, $userB, $blockedTypes) {
                $q->where('user_id', $userA)
                    ->whereIn('type', $blockedTypes)
                    ->where('status', 'open')
                    ->whereJsonContains('details->other_user_ids', $userB);
            })->orWhere(function ($q) use ($userA, $userB, $blockedTypes) {
                $q->where('user_id', $userB)
                    ->whereIn('type', $blockedTypes)
                    ->where('status', 'open')
                    ->whereJsonContains('details->other_user_ids', $userA);
            });
        })->exists();
    }
}
