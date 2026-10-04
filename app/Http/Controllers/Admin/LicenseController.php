<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin license management. Phase 6 will expand this panel;
 * routes/views are kept tidy for extension.
 */
class LicenseController extends Controller
{
    /**
     * List all licenses.
     */
    public function index(): View
    {
        $licenses = License::orderByDesc('created_at')->paginate(20);

        return view('admin.licenses.index', compact('licenses'));
    }

    /**
     * Generate a new license with a random key.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        // Random, URL-safe, human-typable key; retry on the (tiny) chance
        // of a collision with the unique column.
        do {
            $key = strtoupper(Str::random(16));
        } while (License::where('license_key', $key)->exists());

        License::create([
            'license_key' => $key,
            'email' => $validated['email'],
            'status' => 'active',
        ]);

        return back()->with('status', 'License generated: '.$key);
    }

    /**
     * Activate a license.
     */
    public function activate(License $license): RedirectResponse
    {
        $license->update(['status' => 'active']);

        return back()->with('status', 'License activated.');
    }

    /**
     * Disable a license (it can no longer be used to install).
     */
    public function disable(License $license): RedirectResponse
    {
        $license->update(['status' => 'disabled']);

        return back()->with('status', 'License disabled.');
    }

    /**
     * Permanently delete a license.
     */
    public function destroy(License $license): RedirectResponse
    {
        $license->delete();

        return back()->with('status', 'License deleted.');
    }
}
