<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY legal way money moves. Every credit and debit:
 *   1. runs inside a DB transaction,
 *   2. takes a row lock (lockForUpdate) on the user row so concurrent
 *      requests serialize instead of racing,
 *   3. recomputes the balance as SUM(amount_paise) over the ledger —
 *      the ledger is the truth, never the cached column,
 *   4. appends one immutable ledger row with before/after snapshots,
 *   5. mirrors the new balance into users.wallet_balance_paise
 *      (a READ cache; `wallet:reconcile` verifies it matches).
 *
 * Idempotency: each entry carries a caller-supplied reference_id with a
 * UNIQUE constraint. Repeating the same reference (retried callback,
 * double-click) returns the existing entry — money is never moved twice.
 *
 * All amounts are in PAISE as integers. Negative amounts are never
 * accepted by credit/debit — use the correct method so the sign, the
 * transaction type, and the audit trail always agree.
 */
class WalletService
{
    /**
     * Add money. Returns the ledger entry.
     *
     * @throws \InvalidArgumentException on non-positive amount.
     * @throws DuplicateReferenceException when the reference was already used.
     */
    public function credit(User $user, string $type, int $amountPaise, string $referenceId, array $meta = []): WalletLedger
    {
        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return $this->move($user, $type, $amountPaise, $referenceId, $meta);
    }

    /**
     * Remove money (hold/withdrawal/spend). Throws when the balance cannot
     * cover it — no partial debit, no negative wallets.
     *
     * Phase 6 rules:
     *  - a FROZEN wallet cannot be debited at all (WalletFrozenException),
     *  - only the AVAILABLE balance (ledger minus unreleased bonus locks)
     *    can be spent — locked bonus is never spendable,
     *  - a 'bet' debit increments users.wagered_paise and releases any
     *    bonus lock whose wagering requirement is now met.
     *
     * @throws \InvalidArgumentException on non-positive amount.
     * @throws WalletFrozenException when the wallet is frozen.
     * @throws InsufficientBalanceException when funds are insufficient.
     * @throws DuplicateReferenceException when the reference was already used.
     */
    public function debit(User $user, string $type, int $amountPaise, string $referenceId, array $meta = []): WalletLedger
    {
        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $type, $amountPaise, $referenceId, $meta) {
            // Lock first, then read everything INSIDE the lock — otherwise
            // two concurrent debits can both pass the sufficiency check.
            $locked = $user->lockForUpdate()->findOrFail($user->getKey());

            if ($locked->isFrozen()) {
                throw new WalletFrozenException(
                    'This wallet is frozen pending a fraud review.'
                );
            }

            $available = $this->availableBalance($locked);
            if ($available < $amountPaise) {
                throw new InsufficientBalanceException(
                    "Insufficient available balance: have {$available}, need {$amountPaise}."
                );
            }

            $entry = $this->writeEntry($locked, $type, -$amountPaise, $this->ledgerSum($locked), $referenceId, $meta);

            // Every real-money bet counts toward bonus wagering.
            if ($type === 'bet') {
                $locked->increment('wagered_paise', $amountPaise);
                $this->releaseEarnedLocks($locked->fresh());
            }

            return $entry;
        });
    }

    /**
     * Authoritative balance: the ledger SUM. The cached column is ignored.
     */
    public function balance(User $user): int
    {
        return $this->ledgerSum($user);
    }

    /**
     * Spendable balance: ledger sum MINUS unreleased bonus locks.
     * Locked referral bonus sits in the ledger but cannot be spent,
     * withdrawn, or staked until its wagering requirement is met.
     */
    public function availableBalance(User $user): int
    {
        return $this->ledgerSum($user) - $this->lockedBalance($user);
    }

    /**
     * Total paise currently held back by unreleased bonus locks.
     */
    public function lockedBalance(User $user): int
    {
        $lockIds = \App\Models\BonusLock::where('user_id', $user->getKey())
            ->where('released', false)
            ->pluck('ledger_id');

        if ($lockIds->isEmpty()) {
            return 0;
        }

        return (int) \App\Models\WalletLedger::whereIn('id', $lockIds)->sum('amount_paise');
    }

    /**
     * Release every lock whose wagering requirement the user has now met.
     */
    protected function releaseEarnedLocks(User $user): void
    {
        \App\Models\BonusLock::where('user_id', $user->getKey())
            ->where('released', false)
            ->where('required_wager_paise', '<=', $user->wagered_paise)
            ->update(['released' => true]);
    }

    /**
     * Check whether a reference_id was already consumed (idempotency probe).
     */
    public function referenceExists(string $referenceId): bool
    {
        return WalletLedger::where('reference_id', $referenceId)->exists();
    }

    /**
     * Shared write path for credits (credit/debit re-locks then calls this).
     *
     * Phase 6: a 'referral_bonus' credit is immediately wrapped in a
     * BonusLock — the money lands in the ledger (visible) but is NOT
     * spendable until the player wagers bonus x bonus_wager_multiplier.
     */
    protected function move(User $user, string $type, int $amountPaise, string $referenceId, array $meta): WalletLedger
    {
        return DB::transaction(function () use ($user, $type, $amountPaise, $referenceId, $meta) {
            $locked = $user->lockForUpdate()->findOrFail($user->getKey());
            $previous = $this->ledgerSum($locked);

            $entry = $this->writeEntry($locked, $type, $amountPaise, $previous, $referenceId, $meta);

            if ($type === 'referral_bonus') {
                $multiplier = (int) (\App\Models\Setting::get('bonus_wager_multiplier', '5') ?? '5');
                \App\Models\BonusLock::create([
                    'user_id' => $locked->getKey(),
                    'ledger_id' => $entry->id,
                    'required_wager_paise' => $amountPaise * max(1, $multiplier),
                    'released' => false,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Append the ledger row and refresh the cached balance column.
     * The UNIQUE index on reference_id is the final backstop: if two
     * transactions slip past the existence check, the second one fails
     * here and its transaction rolls back (no partial money movement).
     */
    protected function writeEntry(User $lockedUser, string $type, int $signedAmount, int $previousBalance, string $referenceId, array $meta): WalletLedger
    {
        try {
            $entry = WalletLedger::create([
                'user_id' => $lockedUser->getKey(),
                'transaction_type' => $type,
                'amount_paise' => $signedAmount,
                'previous_balance_paise' => $previousBalance,
                'new_balance_paise' => $previousBalance + $signedAmount,
                'reference_id' => $referenceId,
                'meta' => $meta,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // MySQL duplicate-entry on the unique reference_id index.
            if ($e->getCode() === '23000') {
                $existing = WalletLedger::where('reference_id', $referenceId)->first();
                throw new DuplicateReferenceException(
                    "Reference '{$referenceId}' was already processed.",
                    $existing?->id
                );
            }

            throw $e;
        }

        // Read cache only — updated in the same transaction as the entry.
        $lockedUser->update(['wallet_balance_paise' => $entry->new_balance_paise]);

        return $entry;
    }

    protected function ledgerSum(User $user): int
    {
        return (int) WalletLedger::where('user_id', $user->getKey())->sum('amount_paise');
    }
}

/**
 * Thrown when the same reference_id is submitted twice (idempotency).
 * Carries the id of the entry that already owns the reference.
 */
class DuplicateReferenceException extends \RuntimeException
{
    public function __construct(string $message = '', public readonly ?int $existingEntryId = null)
    {
        parent::__construct($message);
    }
}

/**
 * Thrown by debit() when the ledger balance cannot cover the amount.
 */
class InsufficientBalanceException extends \RuntimeException
{
}

/**
 * Thrown by debit() when the user's wallet is frozen (fraud review).
 * Frozen wallets cannot move money out at all — not bets, not withdrawals.
 */
class WalletFrozenException extends \RuntimeException
{
}
