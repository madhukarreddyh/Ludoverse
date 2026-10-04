<?php

namespace App\Http\Controllers;

use App\Services\OtpResendTooSoon;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtpController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    /**
     * Show the mobile verification page.
     */
    public function show(): View
    {
        return view('otp.show');
    }

    /**
     * Issue an OTP to the given mobile number (rate-limited: 1/min).
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,16}$/'],
        ]);

        try {
            $this->otp->send($validated['mobile']);
        } catch (OtpResendTooSoon $e) {
            return back()
                ->withErrors(['mobile' => $e->getMessage()])
                ->setStatusCode(429);
        }

        return back()->with('status', 'OTP sent to '.$validated['mobile'].'. It expires in 10 minutes.');
    }

    /**
     * Verify the submitted code and mark the mobile as verified.
     */
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $error = $this->otp->verify($validated['mobile'], $validated['code']);

        if ($error !== null) {
            return back()->withErrors(['code' => $error])->onlyInput('mobile');
        }

        $user = $request->user();
        $user->forceFill([
            'mobile' => $validated['mobile'],
            'mobile_verified_at' => now(),
        ])->save();

        return redirect()->route('home')->with('status', 'Mobile number verified.');
    }
}
