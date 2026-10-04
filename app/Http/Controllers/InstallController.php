<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallController extends Controller
{
    /**
     * Show the license installation form.
     */
    public function show(): RedirectResponse|View
    {
        if (Setting::bool('license_activated')) {
            return redirect()->route('home');
        }

        return view('install.show');
    }

    /**
     * Validate the license key + email pair and activate the platform.
     * Nothing is activated on any failure path.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $license = License::where('license_key', $validated['license_key'])->first();

        // Key must exist, be active, and belong to the given email
        // (compared case-insensitively).
        $valid = $license !== null
            && $license->status === 'active'
            && strtolower($license->email) === strtolower($validated['email']);

        if (! $valid) {
            return back()
                ->withErrors(['license_key' => 'Invalid license key or email address.'])
                ->onlyInput('email');
        }

        Setting::set('license_activated', '1');

        return redirect()->route('home')->with('status', 'License activated. Welcome to LudoVerse.');
    }
}
