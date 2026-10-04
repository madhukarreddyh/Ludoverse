<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin withdrawal review.
 *
 * Funds were already debited (held) when the user submitted the request,
 * so the state machine is:
 *   pending  -> approve  => 'approved' (funds stay held)
 *   approved -> markPaid  => 'paid'    (operator sent the money)
 *   pending  -> reject   => 'rejected' + COMPENSATING ledger entry that
 *                            returns the held funds to the user's wallet
 *                            (type 'withdrawal', POSITIVE amount).
 */
class WithdrawalController extends Controller
{
    public function index(): View
    {
        return view('admin.withdrawals.index', [
            'withdrawals' => Withdrawal::with('user')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending withdrawals can be approved.']);
        }

        $withdrawal->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Withdrawal approved. Mark it paid once the money is sent.');
    }

    public function markPaid(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if ($withdrawal->status !== 'approved') {
            return back()->withErrors(['status' => 'Only approved withdrawals can be marked paid.']);
        }

        $withdrawal->update([
            'status' => 'paid',
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Withdrawal marked as paid.');
    }

    public function reject(Request $request, Withdrawal $withdrawal, WalletService $wallet): RedirectResponse
    {
        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending withdrawals can be rejected.']);
        }

        DB::transaction(function () use ($withdrawal, $request, $wallet) {
            $withdrawal->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
            ]);

            // Return the held funds via a COMPENSATING entry: type stays
            // 'withdrawal' so the ledger tells the full story, amount is
            // positive to undo the original negative hold.
            $wallet->credit(
                $withdrawal->user,
                'withdrawal',
                $withdrawal->amount_paise,
                'wdr_refund_'.$withdrawal->id,
                [
                    'reversal_of' => 'wdr_hold',
                    'withdrawal_id' => $withdrawal->id,
                    'rejected_by' => $request->user()->id,
                ]
            );
        });

        return back()->with('status', 'Withdrawal rejected and funds returned to the user.');
    }
}
