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

        <hr class="border-slate-200">
        <h2 class="text-xl font-bold">Bot Economy</h2>
        <p class="text-sm text-slate-500">
            Bots fill waiting public tables so players don't wait forever. Difficulty only shifts win
            <em>rates</em> statistically — no setting guarantees an exact win ratio (dice variance).
        </p>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="bot_fill_enabled" value="0">
            <input type="checkbox" name="bot_fill_enabled" value="1" {{ old('bot_fill_enabled', $bot_fill_enabled) ? 'checked' : '' }}>
            Enable bot auto-fill on waiting tables
        </label>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="bot_difficulty" class="block text-sm font-medium mb-1">Bot difficulty</label>
                <select id="bot_difficulty" name="bot_difficulty" class="w-full border rounded px-3 py-2">
                    @foreach (['easy', 'medium', 'hard'] as $d)
                        <option value="{{ $d }}" {{ old('bot_difficulty', $bot_difficulty) === $d ? 'selected' : '' }}>{{ ucfirst($d) }}</option>
                    @endforeach
                </select>
                @error('bot_difficulty')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="bot_max_per_match" class="block text-sm font-medium mb-1">Max bots per match</label>
                <input id="bot_max_per_match" type="number" min="0" max="7" name="bot_max_per_match"
                       value="{{ old('bot_max_per_match', $bot_max_per_match) }}"
                       class="w-full border rounded px-3 py-2">
                @error('bot_max_per_match')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="bot_join_after_seconds" class="block text-sm font-medium mb-1">Seat a bot after (seconds waiting)</label>
                <input id="bot_join_after_seconds" type="number" min="0" max="3600" name="bot_join_after_seconds"
                       value="{{ old('bot_join_after_seconds', $bot_join_after_seconds) }}"
                       class="w-full border rounded px-3 py-2">
                @error('bot_join_after_seconds')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="bot_tables" class="block text-sm font-medium mb-1">Bet levels bots may join (JSON paise)</label>
                <input id="bot_tables" type="text" name="bot_tables"
                       value="{{ old('bot_tables', $bot_tables) }}"
                       class="w-full border rounded px-3 py-2" maxlength="100" placeholder='["500","1000"]'>
                @error('bot_tables')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <hr class="border-slate-200">
        <h2 class="text-xl font-bold">Tournaments</h2>
        <div>
            <label for="tournament_commission_rate" class="block text-sm font-medium mb-1">Tournament commission % (of entry-fee pool)</label>
            <input id="tournament_commission_rate" type="number" step="0.01" min="0" max="50" name="tournament_commission_rate"
                   value="{{ old('tournament_commission_rate', $tournament_commission_rate) }}"
                   class="w-full border rounded px-3 py-2">
            <p class="text-xs text-slate-500 mt-1">Prize pool = entry fees minus this cut. Split: champion 60%, runner-up 25%, third 15%.</p>
            @error('tournament_commission_rate')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        <hr class="border-slate-200">
        <h2 class="text-xl font-bold">Security &amp; anti-cheat (Phase 6)</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="client_version" class="block text-sm font-medium mb-1">Client version (X-Client-Version)</label>
                <input id="client_version" type="text" name="client_version"
                       value="{{ old('client_version', $client_version) }}"
                       class="w-full border rounded px-3 py-2" maxlength="32">
                <p class="text-xs text-slate-500 mt-1">Mismatch on /play JSON endpoints logs a cheat flag (never an instant ban).</p>
                @error('client_version')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cheat_min_move_ms" class="block text-sm font-medium mb-1">Min human move time (ms)</label>
                <input id="cheat_min_move_ms" type="number" min="50" max="5000" name="cheat_min_move_ms"
                       value="{{ old('cheat_min_move_ms', $cheat_min_move_ms) }}"
                       class="w-full border rounded px-3 py-2">
                <p class="text-xs text-slate-500 mt-1">Moves faster than this after the dice are logged as impossible_move_timing.</p>
                @error('cheat_min_move_ms')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cheat_auto_suspend_flags" class="block text-sm font-medium mb-1">Flags that auto-suspend</label>
                <input id="cheat_auto_suspend_flags" type="number" min="1" max="20" name="cheat_auto_suspend_flags"
                       value="{{ old('cheat_auto_suspend_flags', $cheat_auto_suspend_flags) }}"
                       class="w-full border rounded px-3 py-2">
                @error('cheat_auto_suspend_flags')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="cheat_flag_window_hours" class="block text-sm font-medium mb-1">Flag window (hours)</label>
                <input id="cheat_flag_window_hours" type="number" min="1" max="720" name="cheat_flag_window_hours"
                       value="{{ old('cheat_flag_window_hours', $cheat_flag_window_hours) }}"
                       class="w-full border rounded px-3 py-2">
                @error('cheat_flag_window_hours')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="referral_bonus_paise" class="block text-sm font-medium mb-1">Referral bonus (paise)</label>
                <input id="referral_bonus_paise" type="number" min="0" max="1000000" name="referral_bonus_paise"
                       value="{{ old('referral_bonus_paise', $referral_bonus_paise) }}"
                       class="w-full border rounded px-3 py-2">
                <p class="text-xs text-slate-500 mt-1">Credited only after the referee verifies mobile; locked until wagered.</p>
                @error('referral_bonus_paise')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="bonus_wager_multiplier" class="block text-sm font-medium mb-1">Bonus wagering multiplier</label>
                <input id="bonus_wager_multiplier" type="number" min="1" max="50" name="bonus_wager_multiplier"
                       value="{{ old('bonus_wager_multiplier', $bonus_wager_multiplier) }}"
                       class="w-full border rounded px-3 py-2">
                @error('bonus_wager_multiplier')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="risk_wd_ratio" class="block text-sm font-medium mb-1">Risk: withdrawal/deposit ratio</label>
                <input id="risk_wd_ratio" type="number" step="0.01" min="0.1" max="2" name="risk_wd_ratio"
                       value="{{ old('risk_wd_ratio', $risk_wd_ratio) }}"
                       class="w-full border rounded px-3 py-2">
                <p class="text-xs text-slate-500 mt-1">Withdrawals above deposits × this ratio raise bot difficulty one level (capped at hard).</p>
                @error('risk_wd_ratio')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <button type="submit" class="bg-indigo-700 text-white px-5 py-2 rounded hover:bg-indigo-800">Save Settings</button>
    </form>
</div>
@endsection
