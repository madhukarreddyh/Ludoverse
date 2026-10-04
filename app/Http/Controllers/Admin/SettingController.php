<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin site settings (copyright footer + wallet/payment settings).
 */
class SettingController extends Controller
{
    /**
     * Show the settings form.
     */
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'copyright_text' => Setting::get('copyright_text'),
            'show_copyright' => Setting::bool('show_copyright'),
            // Wallet / payments (Phase 3).
            'gateway_razorpay_enabled' => Setting::bool('gateway_razorpay_enabled'),
            'gateway_paytm_enabled' => Setting::bool('gateway_paytm_enabled'),
            'deposit_upi_id' => Setting::get('deposit_upi_id', ''),
            'deposit_qr_image' => Setting::get('deposit_qr_image', ''),
            'withdrawal_commission_rate' => Setting::get('withdrawal_commission_rate', '2'),
            'tds_rate' => Setting::get('tds_rate', '30'),
            // Bot economy (Phase 5).
            'bot_difficulty' => Setting::get('bot_difficulty', 'medium'),
            'bot_fill_enabled' => Setting::bool('bot_fill_enabled'),
            'bot_tables' => Setting::get('bot_tables', '["500","1000"]'),
            'bot_max_per_match' => Setting::get('bot_max_per_match', '1'),
            'bot_join_after_seconds' => Setting::get('bot_join_after_seconds', '20'),
            'tournament_commission_rate' => Setting::get('tournament_commission_rate', '10'),
        ]);
    }

    /**
     * Persist the settings form.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'copyright_text' => ['required', 'string', 'max:500'],
            // Checkbox: present => on, absent => off.
            'show_copyright' => ['nullable', 'boolean'],
            // Wallet / payments.
            'gateway_razorpay_enabled' => ['nullable', 'boolean'],
            'gateway_paytm_enabled' => ['nullable', 'boolean'],
            'deposit_upi_id' => ['nullable', 'string', 'max:100'],
            'deposit_qr_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_qr_image' => ['nullable', 'boolean'],
            // Optional so older forms (e.g. copyright-only submissions) still
            // validate; absent values keep the current setting.
            'withdrawal_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            // Bot economy (Phase 5).
            'bot_difficulty' => ['nullable', 'in:easy,medium,hard'],
            'bot_fill_enabled' => ['nullable', 'boolean'],
            'bot_tables' => ['nullable', 'string', 'max:100'],
            'bot_max_per_match' => ['nullable', 'integer', 'min:0', 'max:7'],
            'bot_join_after_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'tournament_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        Setting::set('copyright_text', $validated['copyright_text']);
        Setting::set('show_copyright', $request->boolean('show_copyright') ? '1' : '0');

        Setting::set('gateway_razorpay_enabled', $request->boolean('gateway_razorpay_enabled') ? '1' : '0');
        Setting::set('gateway_paytm_enabled', $request->boolean('gateway_paytm_enabled') ? '1' : '0');
        Setting::set('deposit_upi_id', $validated['deposit_upi_id'] ?? '');

        if ($request->boolean('remove_qr_image')) {
            Setting::set('deposit_qr_image', '');
        } elseif ($request->hasFile('deposit_qr_image')) {
            Setting::set('deposit_qr_image', $request->file('deposit_qr_image')->store('deposit-qr', 'public'));
        }

        Setting::set('withdrawal_commission_rate', (string) ($validated['withdrawal_commission_rate']
            ?? Setting::get('withdrawal_commission_rate', '2')));
        Setting::set('tds_rate', (string) ($validated['tds_rate']
            ?? Setting::get('tds_rate', '30')));

        // Bot economy (Phase 5): absent values keep the current setting.
        Setting::set('bot_difficulty', $validated['bot_difficulty']
            ?? Setting::get('bot_difficulty', 'medium'));
        Setting::set('bot_fill_enabled', $request->boolean('bot_fill_enabled') ? '1' : '0');
        $botTables = $validated['bot_tables'] ?? Setting::get('bot_tables', '["500","1000"]');
        // Must decode to a JSON array of bet levels; fall back to default
        // on garbage input rather than breaking auto-fill.
        $decoded = json_decode((string) $botTables, true);
        if (! is_array($decoded)) {
            $botTables = '["500","1000"]';
        }
        Setting::set('bot_tables', (string) $botTables);
        Setting::set('bot_max_per_match', (string) ($validated['bot_max_per_match']
            ?? Setting::get('bot_max_per_match', '1')));
        Setting::set('bot_join_after_seconds', (string) ($validated['bot_join_after_seconds']
            ?? Setting::get('bot_join_after_seconds', '20')));
        Setting::set('tournament_commission_rate', (string) ($validated['tournament_commission_rate']
            ?? Setting::get('tournament_commission_rate', '10')));

        return back()->with('status', 'Settings saved.');
    }
}
