<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin site settings (copyright footer text + toggle).
 * Phase 6 will add more settings here.
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
        ]);

        Setting::set('copyright_text', $validated['copyright_text']);
        Setting::set('show_copyright', $request->boolean('show_copyright') ? '1' : '0');

        return back()->with('status', 'Settings saved.');
    }
}
