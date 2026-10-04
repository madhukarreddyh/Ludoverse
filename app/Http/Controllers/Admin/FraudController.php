<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fraud triage: open flags -> confirm (freeze wallet / suspend account)
 * or dismiss. Confirming with "freeze" sets user status 'frozen', which
 * makes WalletService::debit throw for every money-out operation.
 */
class FraudController extends Controller
{
    public function index(Request $request): View
    {
        $query = FraudFlag::with('user')->orderByDesc('id');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'open');
            $status = 'open';
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        return view('admin.fraud.index', [
            'flags' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'type' => $type,
            'types' => FraudFlag::TYPES,
            'openCount' => FraudFlag::where('status', 'open')->count(),
        ]);
    }

    public function show(FraudFlag $fraud): View
    {
        return view('admin.fraud.show', ['flag' => $fraud->load('user')]);
    }

    public function confirm(Request $request, FraudFlag $fraud): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:freeze_wallet,suspend_account,flag_only'],
        ]);

        $fraud->forceFill(['status' => 'confirmed'])->save();

        $user = $fraud->user;
        $note = 'Flag confirmed.';
        if ($user) {
            if ($data['action'] === 'freeze_wallet') {
                $user->forceFill(['status' => 'frozen'])->save();
                $note = "Flag confirmed — @{$user->username}'s wallet frozen.";
            } elseif ($data['action'] === 'suspend_account') {
                $user->forceFill(['status' => 'suspended'])->save();
                $note = "Flag confirmed — @{$user->username} suspended.";
            }
        }

        return redirect()->route('hmkr.fraud.index')->with('status', $note);
    }

    public function dismiss(FraudFlag $fraud): RedirectResponse
    {
        $fraud->forceFill(['status' => 'dismissed'])->save();

        return back()->with('status', 'Flag dismissed.');
    }
}
