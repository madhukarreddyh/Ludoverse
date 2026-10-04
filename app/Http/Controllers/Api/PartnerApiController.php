<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use App\Services\DuplicateReferenceException;
use App\Services\InsufficientBalanceException;
use App\Services\TournamentService;
use App\Services\WalletFrozenException;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Private partner API (/api/v1/partner/*).
 *
 * Auth: Bearer PRIVATE api key + IP whitelist. Everything the public API
 * offers, plus tournament entry and direct wallet debit/credit.
 *
 * SECURITY: debit/credit move REAL money with the partner's authority.
 * Keys must be IP-whitelisted, stored server-side only, and rotated if
 * ever exposed. reference_id gives idempotency — resending a request
 * with the same reference returns the original entry, never a second
 * movement of money.
 */
class PartnerApiController extends Controller
{
    public function __construct(
        protected WalletService $wallets,
        protected TournamentService $tournaments,
    ) {}

    public function tournamentList(): JsonResponse
    {
        return response()->json([
            'tournaments' => Tournament::orderByDesc('id')->limit(50)->get()->map(fn (Tournament $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'status' => $t->status,
                'entry_fee_paise' => $t->entry_fee_paise,
            ]),
        ]);
    }

    public function tournamentJoin(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $tournament = Tournament::find($id);
        $user = User::find($data['user_id']);
        if (! $tournament || ! $user) {
            return response()->json(['error' => 'Tournament or user not found.'], 404);
        }

        try {
            $this->tournaments->register($tournament, $user);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['joined' => true, 'tournament_id' => $tournament->id]);
    }

    public function walletDebit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'reference_id' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::find($data['user_id']);
        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        try {
            $entry = $this->wallets->debit(
                $user, 'partner_debit', (int) $data['amount_paise'],
                'partner_'.$data['reference_id'],
                ['reason' => $data['reason'] ?? null, 'key' => $request->attributes->get('api_key')?->name],
            );
        } catch (DuplicateReferenceException $e) {
            return response()->json([
                'duplicate' => true,
                'entry_id' => $e->existingEntryId,
                'note' => 'This reference_id was already processed.',
            ], 200);
        } catch (InsufficientBalanceException | WalletFrozenException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'entry_id' => $entry->id,
            'new_balance_paise' => $entry->new_balance_paise,
        ]);
    }

    public function walletCredit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'amount_paise' => ['required', 'integer', 'min:1'],
            'reference_id' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::find($data['user_id']);
        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        try {
            $entry = $this->wallets->credit(
                $user, 'partner_credit', (int) $data['amount_paise'],
                'partner_'.$data['reference_id'],
                ['reason' => $data['reason'] ?? null, 'key' => $request->attributes->get('api_key')?->name],
            );
        } catch (DuplicateReferenceException $e) {
            return response()->json([
                'duplicate' => true,
                'entry_id' => $e->existingEntryId,
                'note' => 'This reference_id was already processed.',
            ], 200);
        }

        return response()->json([
            'entry_id' => $entry->id,
            'new_balance_paise' => $entry->new_balance_paise,
        ]);
    }
}
