<?php

use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Install (license gate)
|--------------------------------------------------------------------------
| Always reachable — the EnsureLicensed middleware skips these routes so
| the platform can be activated from a fresh, unlicensed state.
*/
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

/*
|--------------------------------------------------------------------------
| Public website pages
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/refund-policy', [PageController::class, 'refund'])->name('refund');
Route::get('/terms-and-conditions', [PageController::class, 'terms'])->name('terms');

/*
|--------------------------------------------------------------------------
| Authentication (server-side sessions)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Email verification (Laravel built-in, via MustVerifyEmail)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verify/resend', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Mobile OTP verification
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/mobile/verify', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/mobile/otp/send', [OtpController::class, 'send'])->name('otp.send');
    Route::post('/mobile/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
});

/*
|--------------------------------------------------------------------------
| Admin panel (Phase 6 will expand this)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('hmkr')->name('hmkr.')->group(function () {
    Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::post('/licenses', [LicenseController::class, 'store'])->name('licenses.store');
    Route::patch('/licenses/{license}/activate', [LicenseController::class, 'activate'])->name('licenses.activate');
    Route::patch('/licenses/{license}/disable', [LicenseController::class, 'disable'])->name('licenses.disable');
    Route::delete('/licenses/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
});
