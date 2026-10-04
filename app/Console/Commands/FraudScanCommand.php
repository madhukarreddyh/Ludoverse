<?php

namespace App\Console\Commands;

use App\Services\FraudScanService;
use Illuminate\Console\Command;

/**
 * Anti-collusion scanner. Creates OPEN fraud_flags for: same-IP / same-
 * device seats at one table, repeated 1v1 opponents (>5/24h), and
 * intentional-loss patterns (>80% losses vs one opponent, min 5 games).
 * Run on a schedule (e.g. every 15 minutes) and/or on demand.
 */
class FraudScanCommand extends Command
{
    protected $signature = 'fraud:scan';

    protected $description = 'Scan recent matches for collusion patterns and raise fraud flags';

    public function handle(FraudScanService $scanner): int
    {
        $created = $scanner->scan();

        $this->info("Fraud scan complete: {$created} new flag(s).");

        return self::SUCCESS;
    }
}
