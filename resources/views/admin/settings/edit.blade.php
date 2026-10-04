@extends('layouts.app')

@section('title', 'Settings — Admin')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold mb-6">Site Settings</h1>

    <form method="POST" action="{{ route('hmkr.settings.update') }}" enctype="multipart/form-data" class="space-y-4 bg-white p-6 rounded-lg shadow">
        @csrf
        <div>
            <label for="copyright_text" class="block text-sm font-medium mb-1">Copyright footer text</label>
            <textarea id="copyright_text" name="copyright_text" rows="2" required
                      class="w-full border rounded px-3 py-2">{{ old('copyright_text', $copyright_text) }}</textarea>
            @error('copyright_text')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            {{-- Hidden field so an unchecked box still submits "off". --}}
            <input type="hidden" name="show_copyright" value="0">
            <input type="checkbox" name="show_copyright" value="1" {{ old('show_copyright', $show_copyright) ? 'checked' : '' }}>
            Show copyright footer
        </label>

        <hr class="border-slate-200">
        <h2 class="text-xl font-bold">Wallet &amp; Payments</h2>

        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="gateway_razorpay_enabled" value="0">
                <input type="checkbox" name="gateway_razorpay_enabled" value="1" {{ old('gateway_razorpay_enabled', $gateway_razorpay_enabled) ? 'checked' : '' }}>
                Razorpay gateway enabled <span class="text-slate-500">(keys from RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET in .env)</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="gateway_paytm_enabled" value="0">
                <input type="checkbox" name="gateway_paytm_enabled" value="1" {{ old('gateway_paytm_enabled', $gateway_paytm_enabled) ? 'checked' : '' }}>
                Paytm gateway enabled <span class="text-slate-500">(stub only in this phase — always off)</span>
            </label>
        </div>

        <div>
            <label for="deposit_upi_id" class="block text-sm font-medium mb-1">Deposit UPI ID (shown on the manual deposit page)</label>
            <input id="deposit_upi_id" type="text" name="deposit_upi_id" value="{{ old('deposit_upi_id', $deposit_upi_id) }}"
                   class="w-full border rounded px-3 py-2" maxlength="100" placeholder="example@upi">
            @error('deposit_upi_id')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="deposit_qr_image" class="block text-sm font-medium mb-1">Deposit UPI QR image</label>
            @if ($deposit_qr_image)
                <img src="{{ asset('storage/'.$deposit_qr_image) }}" alt="Deposit QR" class="w-40 h-40 object-contain border rounded mb-2">
                <label class="flex items-center gap-2 text-sm mb-2">
                    <input type="checkbox" name="remove_qr_image" value="1">
                    Remove current QR image
                </label>
            @endif
            <input id="deposit_qr_image" type="file" name="deposit_qr_image" accept="image/*"
                   class="w-full border rounded px-3 py-2 text-sm">
            @error('deposit_qr_image')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="withdrawal_commission_rate" class="block text-sm font-medium mb-1">Withdrawal commission %</label>
                <input id="withdrawal_commission_rate" type="number" step="0.01" min="0" max="50" name="withdrawal_commission_rate"
                       value="{{ old('withdrawal_commission_rate', $withdrawal_commission_rate) }}" required
                       class="w-full border rounded px-3 py-2">
                @error('withdrawal_commission_rate')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="tds_rate" class="block text-sm font-medium mb-1">TDS % on withdrawals</label>
                <input id="tds_rate" type="number" step="0.01" min="0" max="50" name="tds_rate"
                       value="{{ old('tds_rate', $tds_rate) }}" required
                       class="w-full border rounded px-3 py-2">
                @error('tds_rate')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Save Settings</button>
    </form>
</div>
@endsection
