<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\WalletLedger;
use App\Services\DuplicateReferenceException;
use App\Services\InsufficientBalanceException;
use App\Services\WalletService;
use Tests\Feature\LicensedTestCase;

/**
 * The ledger is the source of truth: balance() must always equal
 * SUM(amount_paise), and duplicate reference_ids must never double-move money.
 */
class LedgerIntegrityTest extends LicensedTestCase
{
    protected WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    /**
     * Randomized integrity check, run 10 times inside one method:
     * random credits/debits across 5 users, then assert every user's
     * balance() equals their ledger SUM and the cached column agrees.
     */
    public function test_randomized_ledger_integrity(): void
    {
        $expected = [];

        for ($round = 0; $round < 10; $round++) {
            $users = User::factory()->count(5)->create();
            $expected = [];

            for ($i = 0; $i < 60; $i++) {
                $user = $users->random();
                $id = $user->id;
                $expected[$id] ??= 0;

                $amount = random_int(100, 50000); // ₹1 – ₹500
                $ref = "round{$round}_op{$i}_user{$id}_".uniqid();

                if (random_int(0, 1) === 0 || $expected[$id] < $amount) {
                    $this->wallet->credit($user, 'deposit', $amount, $ref);
                    $expected[$id] += $amount;
                } else {
                    $this->wallet->debit($user, 'bet', $amount, $ref);
                    $expected[$id] -= $amount;
                }
            }

            foreach ($users as $user) {
                $id = $user->id;
                // Authoritative: balance() == SUM(amount_paise).
                $this->assertSame(
                    $expected[$id],
                    $this->wallet->balance($user),
                    "round {$round}: balance() mismatch for user {$id}"
                );
                $this->assertSame(
                    $expected[$id],
                    (int) WalletLedger::where('user_id', $id)->sum('amount_paise'),
                    "round {$round}: raw SUM mismatch for user {$id}"
                );
                // Cached column must mirror the ledger.
                $this->assertSame(
                    $expected[$id],
                    (int) $user->fresh()->wallet_balance_paise,
                    "round {$round}: cached column drift for user {$id}"
                );
            }
        }
    }

    public function test_duplicate_reference_id_is_rejected_without_double_credit(): void
    {
        $user = User::factory()->create();

        $first = $this->wallet->credit($user, 'deposit', 5000, 'ref_dup_1');
        $this->assertSame(5000, $this->wallet->balance($user));

        $thrown = null;
        try {
            $this->wallet->credit($user, 'deposit', 5000, 'ref_dup_1');
        } catch (DuplicateReferenceException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'DuplicateReferenceException was not thrown.');
        $this->assertSame($first->id, $thrown->existingEntryId, 'Exception should point at the existing entry.');
        $this->assertSame(5000, $this->wallet->balance($user), 'Balance must not move on duplicate.');
        $this->assertSame(1, WalletLedger::where('reference_id', 'ref_dup_1')->count());
    }

    public function test_duplicate_debit_reference_does_not_double_debit(): void
    {
        $user = User::factory()->create();
        $this->wallet->credit($user, 'deposit', 10000, 'seed_1');

        $this->wallet->debit($user, 'bet', 3000, 'ref_dup_debit');

        try {
            $this->wallet->debit($user, 'bet', 3000, 'ref_dup_debit');
            $this->fail('Expected DuplicateReferenceException.');
        } catch (DuplicateReferenceException) {
            // expected
        }

        $this->assertSame(7000, $this->wallet->balance($user));
    }

    public function test_debit_beyond_balance_throws_and_moves_nothing(): void
    {
        $user = User::factory()->create();
        $this->wallet->credit($user, 'deposit', 2000, 'seed_2');

        try {
            $this->wallet->debit($user, 'bet', 2001, 'overdraw_1');
            $this->fail('Expected InsufficientBalanceException.');
        } catch (InsufficientBalanceException) {
            // expected
        }

        $this->assertSame(2000, $this->wallet->balance($user));
    }

    public function test_ledger_snapshots_track_previous_and_new_balance(): void
    {
        $user = User::factory()->create();

        $a = $this->wallet->credit($user, 'deposit', 5000, 'snap_1');
        $this->assertSame(0, $a->previous_balance_paise);
        $this->assertSame(5000, $a->new_balance_paise);

        $b = $this->wallet->debit($user, 'bet', 1500, 'snap_2');
        $this->assertSame(5000, $b->previous_balance_paise);
        $this->assertSame(3500, $b->new_balance_paise);
    }
}
