@extends('layouts.app')

@section('title', 'Deposit — LudoVerse')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Deposit Money</h1>

    <div class="grid md:grid-cols-2 gap-6">
        {{-- Online deposit via gateway --}}
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-3">Pay online (Razorpay)</h2>
            @if ($razorpay_enabled)
                <form id="gateway-form" class="space-y-3">
                    <div>
                        <label for="gw-amount" class="block text-sm font-medium mb-1">Amount (₹)</label>
                        <input id="gw-amount" type="number" min="10" max="100000" step="1" value="100" required
                               class="w-full border rounded px-3 py-2">
                        <p class="text-xs text-slate-500 mt-1">Minimum ₹10, maximum ₹1,00,000.</p>
                    </div>
                    <p id="gw-error" class="text-red-600 text-sm hidden"></p>
                    <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Pay securely</button>
                </form>
            @else
                <p class="text-slate-500 text-sm">Online payments are currently unavailable. Please use the manual UPI deposit below.</p>
            @endif
        </div>

        {{-- Manual UPI deposit --}}
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-bold mb-3">Manual UPI deposit</h2>
            @if ($deposit_qr_image)
                <img src="{{ asset('storage/'.$deposit_qr_image) }}" alt="Deposit QR code" class="w-44 h-44 object-contain border rounded mb-2">
            @endif
            @if ($deposit_upi_id)
                <p class="text-sm mb-1">Pay to UPI ID: <span class="font-mono font-semibold">{{ $deposit_upi_id }}</span></p>
            @endif
            <form method="POST" action="{{ route('wallet.deposit.manual') }}" enctype="multipart/form-data" class="space-y-3 mt-4">
                @csrf
                <div>
                    <label for="manual-amount" class="block text-sm font-medium mb-1">Amount paid (₹)</label>
                    <input id="manual-amount" type="number" name="amount" min="10" max="100000" step="1" value="{{ old('amount') }}" required
                           class="w-full border rounded px-3 py-2">
                    @error('amount')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="utr" class="block text-sm font-medium mb-1">UTR / transaction reference</label>
                    <input id="utr" type="text" name="utr" value="{{ old('utr') }}" required maxlength="100"
                           class="w-full border rounded px-3 py-2" placeholder="12-digit UTR from your payment app">
                    @error('utr')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="screenshot" class="block text-sm font-medium mb-1">Payment screenshot</label>
                    <input id="screenshot" type="file" name="screenshot" accept="image/*" required
                           class="w-full border rounded px-3 py-2 text-sm">
                    @error('screenshot')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="bg-emerald-600 text-white px-5 py-2 rounded hover:bg-emerald-700">Submit deposit claim</button>
            </form>
        </div>
    </div>
</div>

@if ($razorpay_enabled)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('gateway-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const err = document.getElementById('gw-error');
    err.classList.add('hidden');
    const rupees = parseInt(document.getElementById('gw-amount').value, 10);
    if (!rupees || rupees < 10 || rupees > 100000) { err.textContent = 'Enter an amount between ₹10 and ₹1,00,000.'; err.classList.remove('hidden'); return; }

    const res = await fetch('{{ route('wallet.deposit.order') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ amount: rupees * 100 }) // send paise
    });
    const data = await res.json();
    if (!res.ok) { err.textContent = data.message || 'Could not start payment.'; err.classList.remove('hidden'); return; }

    const rzp = new Razorpay({
        key: data.key_id,
        amount: data.amount_paise,
        currency: data.currency,
        order_id: data.gateway_order_id,
        name: 'LudoVerse',
        description: 'Wallet deposit',
        handler: async function (response) {
            // Forward Razorpay's signed result to our callback for verification.
            const cb = await fetch('{{ route('wallet.deposit.callback') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify(response)
            });
            if (cb.ok) { window.location.href = '{{ route('wallet.index') }}'; }
            else {
                const d = await cb.json();
                err.textContent = d.message || 'Payment verification failed.';
                err.classList.remove('hidden');
            }
        }
    });
    rzp.open();
});
</script>
@endif
@endsection
