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

        return back()->with('status', 'Settings saved.');
    }
}
