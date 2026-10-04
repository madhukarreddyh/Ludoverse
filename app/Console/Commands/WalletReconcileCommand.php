<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Console\Command;

/**
 * Verify every user's cached wallet_balance_paise matches the authoritative
 * ledger SUM(amount_paise). Exit code 0 = all clean, 1 = mismatches found.
 */
class WalletReconcileCommand extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'Check cached wallet balances against the authoritative ledger sums';

    public function handle(): int
    {
        $checked = 0;
        $mismatches = 0;

        User::query()->chunkById(500, function ($users) use (&$checked, &$mismatches) {
            $ids = $users->pluck('id');

            // One grouped query per chunk instead of N+1.
            $sums = WalletLedger::whereIn('user_id', $ids)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(amount_paise) as total')
                ->pluck('total', 'user_id');

            foreach ($users as $user) {
                $checked++;
                $ledgerSum = (int) ($sums[$user->id] ?? 0);
                $cached = (int) ($user->wallet_balance_paise ?? 0);

                if ($ledgerSum !== $cached) {
                    $mismatches++;
                    $this->error(
                        "MISMATCH user #{$user->id} ({$user->username}): ledger={$ledgerSum} cached={$cached} diff=".
                        ($ledgerSum - $cached)
                    );
                }
            }
        });

        if ($mismatches === 0) {
            $this->info("OK: {$checked} user(s) reconciled, no mismatches.");
            return self::SUCCESS;
        }

        $this->warn("{$mismatches} mismatch(es) out of {$checked} user(s).");
        return self::FAILURE;
    }
}
