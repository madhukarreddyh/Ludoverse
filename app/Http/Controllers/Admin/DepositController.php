<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualDeposit;
use App\Services\DuplicateReferenceException;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin review of manual (UPI/bank) deposit claims.
 * Approve => ledger credit (idempotent on reference `manual_{id}`).
 * Reject  => status only; no money moves.
 */
class DepositController extends Controller
{
    public function index(): View
    {
        return view('admin.deposits.index', [
            'deposits' => ManualDeposit::with('user')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function approve(Request $request, ManualDeposit $deposit, WalletService $wallet): RedirectResponse
    {
        if ($deposit->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending deposits can be approved.']);
        }

        try {
            DB::transaction(function () use ($deposit, $request, $wallet) {
                $deposit->update([
                    'status' => 'approved',
                    'reviewed_by' => $request->user()->id,
                ]);

                $wallet->credit(
                    $deposit->user,
                    'deposit',
                    $deposit->amount_paise,
                    'manual_'.$deposit->id,
                    [
                        'manual_deposit_id' => $deposit->id,
                        'utr' => $deposit->utr,
                        'approved_by' => $request->user()->id,
                    ]
                );
            });
        } catch (DuplicateReferenceException) {
            // Already credited (e.g. double-clicked approve): keep the
            // approved status, no second credit.
            return back()->with('status', 'Already credited — duplicate approve ignored.');
        }

        return back()->with('status', 'Deposit approved and credited.');
    }

    public function reject(Request $request, ManualDeposit $deposit): RedirectResponse
    {
        if ($deposit->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending deposits can be rejected.']);
        }

        $deposit->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Deposit rejected. No money was credited.');
    }
}
