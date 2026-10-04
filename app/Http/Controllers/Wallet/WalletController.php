<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\ManualDeposit;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Services\DuplicateReferenceException;
use App\Services\InsufficientBalanceException;
use App\Services\Payments\GatewayNotAvailableException;
use App\Services\Payments\PaytmGateway;
use App\Services\Payments\RazorpayGateway;
use App\Services\WalletService;
use App\Services\WithdrawalBreakdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(protected WalletService $wallet)
    {
    }

    /**
     * Wallet overview: authoritative balance + paginated ledger history.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('wallet.index', [
            // balance() reads the ledger SUM — the source of truth.
            'balance_paise' => $this->wallet->balance($user),
            'cached_balance_paise' => (int) ($user->wallet_balance_paise ?? 0),
            'entries' => $user->walletEntries()->latest()->paginate(20),
        ]);
    }

    /**
     * Deposit page: Razorpay form, manual UPI form, QR display.
     */
    public function deposit(Request $request): View
    {
        return view('wallet.deposit', [
            'razorpay_enabled' => (new RazorpayGateway())->isEnabled(),
            'razorpay_key_id' => config('services.razorpay.key_id'),
            'deposit_upi_id' => Setting::get('deposit_upi_id', ''),
            'deposit_qr_image' => Setting::get('deposit_qr_image', ''),
        ]);
    }

    /**
     * Create a pending deposit + gateway order; returns checkout params as
     * JSON for the Razorpay checkout.js on the deposit page.
     */
    public function createGatewayOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:10000000'], // ₹10 – ₹1,00,000
            'gateway' => ['nullable', 'string', 'in:razorpay,paytm'],
        ]);

        $gateway = match ($validated['gateway'] ?? 'razorpay') {
            'paytm' => new PaytmGateway(),
            default => new RazorpayGateway(),
        };

        try {
            $order = $gateway->createOrder($validated['amount'], [
                'user_id' => $request->user()->id,
            ]);
        } catch (GatewayNotAvailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $deposit = Deposit::create([
            'user_id' => $request->user()->id,
            'gateway' => $gateway->name(),
            'gateway_order_id' => $order['gateway_order_id'],
            'amount_paise' => $validated['amount'],
            'status' => 'pending',
        ]);

        return response()->json([
            'deposit_id' => $deposit->id,
            'gateway_order_id' => $order['gateway_order_id'],
            'amount_paise' => $order['amount'],
            'currency' => $order['currency'] ?? 'INR',
            'key_id' => $order['key_id'] ?? null,
        ]);
    }

    /**
     * Razorpay checkout callback (POST from our own JS after checkout).
     * Verifies the HMAC signature, then credits the ledger exactly once —
     * a repeated callback for the same payment is acknowledged WITHOUT
     * creating a second ledger entry.
     */
    public function depositCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $gateway = new RazorpayGateway();

        $deposit = Deposit::where('gateway_order_id', $validated['razorpay_order_id'])
            ->where('gateway', 'razorpay')
            ->first();

        if (! $deposit) {
            return response()->json(['message' => 'Unknown order.'], 404);
        }

        // IDEMPOTENCY: already processed (or a duplicate callback for a
        // payment we already recorded) => success, no new ledger entry.
        if ($deposit->status === 'completed'
            || Deposit::where('gateway_payment_id', $validated['razorpay_payment_id'])->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if (! $gateway->verifyCallback($validated, $validated['razorpay_signature'])) {
            $deposit->update(['status' => 'failed']);
            return response()->json(['message' => 'Signature verification failed.'], 422);
        }

        try {
            DB::transaction(function () use ($deposit, $validated) {
                // Re-lock the deposit row: a concurrent duplicate callback
                // waits here, then sees 'completed' and returns idempotent.
                $deposit->lockForUpdate()->findOrFail($deposit->id);
                $deposit->refresh();

                if ($deposit->status === 'completed') {
                    return; // lost the race to the other callback — fine.
                }

                $deposit->update([
                    'status' => 'completed',
                    'gateway_payment_id' => $validated['razorpay_payment_id'],
                ]);

                $this->wallet->credit(
                    $deposit->user,
                    'deposit',
                    $deposit->amount_paise,
                    'rzp_'.$validated['razorpay_payment_id'],
                    [
                        'gateway' => 'razorpay',
                        'gateway_order_id' => $validated['razorpay_order_id'],
                        'deposit_id' => $deposit->id,
                    ]
                );
            });
        } catch (DuplicateReferenceException) {
            // The payment was already credited (reference idempotency) —
            // still a success from the user's perspective.
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Manual deposit claim: user paid via UPI, submits amount + UTR + proof.
     */
    public function storeManualDeposit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'utr' => ['required', 'string', 'max:100', 'unique:manual_deposits,utr'],
            'screenshot' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $validated['screenshot']->store('deposits', 'public');

        ManualDeposit::create([
            'user_id' => $request->user()->id,
            'amount_paise' => $validated['amount'],
            'utr' => $validated['utr'],
            'screenshot_path' => $path,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Deposit claim submitted. It will reflect in your wallet after admin approval.');
    }

    /**
     * Withdrawal form (shows the server-computed breakdown preview endpoint).
     */
    public function withdraw(Request $request): View
    {
        return view('wallet.withdraw', [
            'balance_paise' => $this->wallet->balance($request->user()),
        ]);
    }

    /**
     * Server-computed breakdown preview (before any money moves).
     */
    public function withdrawPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:10000000'], // min ₹100
        ]);

        return response()->json(WithdrawalBreakdown::for($validated['amount']));
    }

    /**
     * Submit a withdrawal: the full amount is debited immediately (held)
     * and a pending request row is created in one transaction.
     */
    public function withdrawStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:10000000'],
            'method' => ['required', 'string', 'in:upi,bank'],
            'upi_id' => ['required_if:method,upi', 'nullable', 'string', 'max:100'],
            'account_no' => ['required_if:method,bank', 'nullable', 'string', 'max:50'],
            'ifsc' => ['required_if:method,bank', 'nullable', 'string', 'max:20'],
            'account_name' => ['required_if:method,bank', 'nullable', 'string', 'max:100'],
        ]);

        $details = $validated['method'] === 'upi'
            ? ['upi_id' => $validated['upi_id']]
            : [
                'account_no' => $validated['account_no'],
                'ifsc' => $validated['ifsc'],
                'account_name' => $validated['account_name'],
            ];

        try {
            DB::transaction(function () use ($request, $validated, $details) {
                $breakdown = WithdrawalBreakdown::for($validated['amount']);

                // Debit first (holds the funds); row creation failure rolls
                // the debit back too, so money is never stranded.
                $entry = $this->wallet->debit(
                    $request->user(),
                    'withdrawal',
                    $validated['amount'],
                    'wdr_hold_'.uniqid('', true),
                    ['method' => $validated['method']]
                );

                Withdrawal::create([
                    'user_id' => $request->user()->id,
                    'amount_paise' => $validated['amount'],
                    'method' => $validated['method'],
                    'details' => $details,
                    'tds_paise' => $breakdown['tds'],
                    'commission_paise' => $breakdown['commission'],
                    'net_paise' => $breakdown['net'],
                    'status' => 'pending',
                ]);

                // Stash the hold entry id in meta so admin reversal can
                // reference exactly which entry held the funds.
                $entry->update(['meta' => array_merge($entry->meta ?? [], ['purpose' => 'withdrawal_hold'])]);
            });
        } catch (InsufficientBalanceException $e) {
            // ValidationException => 302 back for web forms, 422 JSON for
            // API-style requests (the QA contract for this failure is 422).
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('wallet.index')->with('status', 'Withdrawal request submitted.');
    }
}
