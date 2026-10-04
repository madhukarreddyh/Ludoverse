<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Withdrawal breakdown math (all in paise, integers):
 *
 *   commission = round(amount * withdrawal_commission_rate / 100)
 *   tds        = round(amount * tds_rate / 100)        // 30% on winnings, per settings
 *   net        = amount - tds - commission            // what the user actually receives
 *
 * Rates are admin-tunable via settings (withdrawal_commission_rate defaults
 * to 2, tds_rate defaults to 30). round() keeps every value a whole paisa —
 * money is never stored as a float.
 */
class WithdrawalBreakdown
{
    /**
     * @return array{amount:int,commission:int,tds:int,net:int,commission_rate:float,tds_rate:float}
     */
    public static function for(int $amountPaise): array
    {
        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        }

        $commissionRate = (float) (Setting::get('withdrawal_commission_rate', '2'));
        $tdsRate = (float) (Setting::get('tds_rate', '30'));

        $commission = (int) round($amountPaise * $commissionRate / 100);
        $tds = (int) round($amountPaise * $tdsRate / 100);
        $net = $amountPaise - $tds - $commission;

        return [
            'amount' => $amountPaise,
            'commission' => $commission,
            'tds' => $tds,
            'net' => $net,
            'commission_rate' => $commissionRate,
            'tds_rate' => $tdsRate,
        ];
    }
}
