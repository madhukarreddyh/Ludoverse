<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the signup form.
     */
    public function showSignup(): View
    {
        return view('auth.signup');
    }

    /**
     * Register a new user. All three policy checkboxes are mandatory;
     * acceptance timestamps are recorded for audit purposes.
     */
    public function signup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{7,16}$/', 'unique:users,mobile'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'referral_code' => ['nullable', 'string', 'max:50'],
            // Policy acceptance: the user must tick every box.
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
            'refund' => ['accepted'],
        ]);

        // Generate a unique referral code belonging to this new user.
        do {
            $myReferralCode = strtoupper(Str::random(8));
        } while (User::where('my_referral_code', $myReferralCode)->exists());

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            'password' => Hash::make($validated['password']),
            'referral_code' => $validated['referral_code'] ?? null,
            'my_referral_code' => $myReferralCode,
            'terms_accepted_at' => now(),
            'privacy_accepted_at' => now(),
            'refund_accepted_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    /**
     * Show the login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Log in with either an email address or a username.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Decide which credential column to check against.
        $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $validated['login'], 'password' => $validated['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('home'));
        }

        return back()->withErrors([
            'login' => 'These credentials do not match our records.',
        ])->onlyInput('login');
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
