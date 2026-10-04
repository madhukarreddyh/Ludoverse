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
     * @throws \InvalidArgumentException on non-positive amount.
     * @throws InsufficientBalanceException when funds are insufficient.
     * @throws DuplicateReferenceException when the reference was already used.
     */
    public function debit(User $user, string $type, int $amountPaise, string $referenceId, array $meta = []): WalletLedger
    {
        if ($amountPaise <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $type, $amountPaise, $referenceId, $meta) {
            // Lock first, then read the balance INSIDE the lock — otherwise two
            // concurrent debits can both pass the sufficiency check.
            $locked = $user->lockForUpdate()->findOrFail($user->getKey());
            $balance = $this->ledgerSum($locked);

            if ($balance < $amountPaise) {
                throw new InsufficientBalanceException(
                    "Insufficient balance: have {$balance}, need {$amountPaise}."
                );
            }

            return $this->writeEntry($locked, $type, -$amountPaise, $balance, $referenceId, $meta);
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
     * Check whether a reference_id was already consumed (idempotency probe).
     */
    public function referenceExists(string $referenceId): bool
    {
        return WalletLedger::where('reference_id', $referenceId)->exists();
    }

    /**
     * Shared write path for credits (credit/debit re-locks then calls this).
     */
    protected function move(User $user, string $type, int $amountPaise, string $referenceId, array $meta): WalletLedger
    {
        return DB::transaction(function () use ($user, $type, $amountPaise, $referenceId, $meta) {
            $locked = $user->lockForUpdate()->findOrFail($user->getKey());
            $previous = $this->ledgerSum($locked);

            return $this->writeEntry($locked, $type, $amountPaise, $previous, $referenceId, $meta);
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
