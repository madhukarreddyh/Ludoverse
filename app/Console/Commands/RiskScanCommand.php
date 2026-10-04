<?php

namespace App\Console\Commands;

use App\Services\RiskService;
use Illuminate\Console\Command;

/**
 * Risk manager: prints today's money picture and reacts — withdrawal
 * spikes raise bot difficulty one level (capped at hard) with an admin
 * notice; high-win-rate players get OPEN high_winrate flags so
 * matchmaking seats harder bots against them.
 */
class RiskScanCommand extends Command
{
    protected $signature = 'risk:scan';

    protected $description = 'Run the risk manager: deposits/withdrawals/profit, bot difficulty, win-rate flags';

    public function handle(RiskService $risk): int
    {
        $result = $risk->scan();
        $n = $result['numbers'];

        $this->table(
            ['Metric', 'Today (₹)'],
            [
                ['Deposits', number_format($n['deposits'] / 100, 2)],
                ['Withdrawals', number_format($n['withdrawals'] / 100, 2)],
                ['Win payouts', number_format($n['win_payouts'] / 100, 2)],
                ['Commissions', number_format($n['commissions'] / 100, 2)],
                ['Platform profit', number_format($n['profit'] / 100, 2)],
            ]
        );

        $this->info('Bot difficulty raised: '.($result['bot_difficulty_raised'] ? 'yes' : 'no'));
        $this->info('High-win-rate flags created: '.$result['high_winrate_flags']);

        return self::SUCCESS;
    }
}
