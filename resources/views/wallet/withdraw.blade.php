@extends('layouts.app')

@section('title', 'Withdraw — LudoVerse')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-2">Withdraw Money</h1>
    <p class="text-slate-600 mb-6">Available balance: <span class="font-semibold">₹{{ number_format($balance_paise / 100, 2) }}</span></p>

    <form method="POST" action="{{ route('wallet.withdraw.store') }}" id="withdraw-form" class="bg-white p-6 rounded-lg shadow space-y-4">
        @csrf
        <div>
            <label for="amount" class="block text-sm font-medium mb-1">Amount (₹, minimum ₹100)</label>
            <input id="amount" type="number" name="amount" min="100" max="100000" step="1" value="{{ old('amount') }}" required
                   class="w-full border rounded px-3 py-2">
            @error('amount')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="method" class="block text-sm font-medium mb-1">Payout method</label>
            <select id="method" name="method" class="w-full border rounded px-3 py-2">
                <option value="upi" {{ old('method') === 'upi' ? 'selected' : '' }}>UPI</option>
                <option value="bank" {{ old('method') === 'bank' ? 'selected' : '' }}>Bank transfer</option>
            </select>
        </div>

        <div id="upi-fields">
            <label for="upi_id" class="block text-sm font-medium mb-1">Your UPI ID</label>
            <input id="upi_id" type="text" name="upi_id" value="{{ old('upi_id') }}" maxlength="100"
                   class="w-full border rounded px-3 py-2" placeholder="yourname@upi">
            @error('upi_id')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div id="bank-fields" class="space-y-3 hidden">
            <div>
                <label for="account_no" class="block text-sm font-medium mb-1">Account number</label>
                <input id="account_no" type="text" name="account_no" value="{{ old('account_no') }}" maxlength="50" class="w-full border rounded px-3 py-2">
                @error('account_no')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="ifsc" class="block text-sm font-medium mb-1">IFSC</label>
                <input id="ifsc" type="text" name="ifsc" value="{{ old('ifsc') }}" maxlength="20" class="w-full border rounded px-3 py-2">
                @error('ifsc')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="account_name" class="block text-sm font-medium mb-1">Account holder name</label>
                <input id="account_name" type="text" name="account_name" value="{{ old('account_name') }}" maxlength="100" class="w-full border rounded px-3 py-2">
                @error('account_name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Server-computed breakdown: shown live, before confirmation. --}}
        <div id="breakdown" class="hidden bg-slate-50 border rounded p-4 text-sm space-y-1">
            <p class="font-bold mb-1">Breakdown</p>
            <p class="flex justify-between"><span>Withdrawal amount</span><span id="bd-amount">–</span></p>
            <p class="flex justify-between"><span>TDS (<span id="bd-tds-rate">–</span>%)</span><span id="bd-tds">–</span></p>
            <p class="flex justify-between"><span>Commission (<span id="bd-comm-rate">–</span>%)</span><span id="bd-commission">–</span></p>
            <p class="flex justify-between font-bold border-t pt-1"><span>You receive</span><span id="bd-net">–</span></p>
        </div>

        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Confirm withdrawal</button>
    </form>
</div>

<script>
const methodSel = document.getElementById('method');
const upiFields = document.getElementById('upi-fields');
const bankFields = document.getElementById('bank-fields');
const amountInput = document.getElementById('amount');
const breakdown = document.getElementById('breakdown');
const fmt = p => '₹' + (p / 100).toFixed(2);

function toggleFields() {
    const isUpi = methodSel.value === 'upi';
    upiFields.classList.toggle('hidden', !isUpi);
    bankFields.classList.toggle('hidden', isUpi);
}

async function preview() {
    const rupees = parseInt(amountInput.value, 10);
    if (!rupees || rupees < 100) { breakdown.classList.add('hidden'); return; }
    const res = await fetch('{{ route('wallet.withdraw.preview') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ amount: rupees * 100 })
    });
    if (!res.ok) { breakdown.classList.add('hidden'); return; }
    const d = await res.json();
    document.getElementById('bd-amount').textContent = fmt(d.amount);
    document.getElementById('bd-tds').textContent = fmt(d.tds);
    document.getElementById('bd-tds-rate').textContent = d.tds_rate;
    document.getElementById('bd-commission').textContent = fmt(d.commission);
    document.getElementById('bd-comm-rate').textContent = d.commission_rate;
    document.getElementById('bd-net').textContent = fmt(d.net);
    breakdown.classList.remove('hidden');
}

methodSel.addEventListener('change', toggleFields);
amountInput.addEventListener('input', preview);
toggleFields();
</script>
@endsection
