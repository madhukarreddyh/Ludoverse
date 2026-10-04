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
            // Security & anti-cheat (Phase 6).
            'client_version' => Setting::get('client_version', '1.0.0'),
            'cheat_min_move_ms' => Setting::get('cheat_min_move_ms', '200'),
            'cheat_auto_suspend_flags' => Setting::get('cheat_auto_suspend_flags', '3'),
            'cheat_flag_window_hours' => Setting::get('cheat_flag_window_hours', '24'),
            'referral_bonus_paise' => Setting::get('referral_bonus_paise', '10000'),
            'bonus_wager_multiplier' => Setting::get('bonus_wager_multiplier', '5'),
            'risk_wd_ratio' => Setting::get('risk_wd_ratio', '0.9'),
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
            // Security & anti-cheat (Phase 6).
            'client_version' => ['nullable', 'string', 'max:32'],
            'cheat_min_move_ms' => ['nullable', 'integer', 'min:50', 'max:5000'],
            'cheat_auto_suspend_flags' => ['nullable', 'integer', 'min:1', 'max:20'],
            'cheat_flag_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'referral_bonus_paise' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'bonus_wager_multiplier' => ['nullable', 'integer', 'min:1', 'max:50'],
            'risk_wd_ratio' => ['nullable', 'numeric', 'min:0.1', 'max:2'],
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

        // Security & anti-cheat (Phase 6): absent values keep the current setting.
        Setting::set('client_version', (string) ($validated['client_version']
            ?? Setting::get('client_version', '1.0.0')));
        Setting::set('cheat_min_move_ms', (string) ($validated['cheat_min_move_ms']
            ?? Setting::get('cheat_min_move_ms', '200')));
        Setting::set('cheat_auto_suspend_flags', (string) ($validated['cheat_auto_suspend_flags']
            ?? Setting::get('cheat_auto_suspend_flags', '3')));
        Setting::set('cheat_flag_window_hours', (string) ($validated['cheat_flag_window_hours']
            ?? Setting::get('cheat_flag_window_hours', '24')));
        Setting::set('referral_bonus_paise', (string) ($validated['referral_bonus_paise']
            ?? Setting::get('referral_bonus_paise', '10000')));
        Setting::set('bonus_wager_multiplier', (string) ($validated['bonus_wager_multiplier']
            ?? Setting::get('bonus_wager_multiplier', '5')));
        Setting::set('risk_wd_ratio', (string) ($validated['risk_wd_ratio']
            ?? Setting::get('risk_wd_ratio', '0.9')));

        return back()->with('status', 'Settings saved.');
    }
}
