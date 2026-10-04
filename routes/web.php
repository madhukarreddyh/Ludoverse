<?php

use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TournamentController as AdminTournamentController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\Wallet\WalletController;
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
| Wallet (money in paise; ledger is the source of truth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('wallet')->name('wallet.')->group(function () {
    Route::get('/', [WalletController::class, 'index'])->name('index');
    Route::get('/deposit', [WalletController::class, 'deposit'])->name('deposit');
    Route::post('/deposit/order', [WalletController::class, 'createGatewayOrder'])->name('deposit.order');
    Route::post('/deposit/callback', [WalletController::class, 'depositCallback'])->name('deposit.callback');
    Route::post('/deposit/manual', [WalletController::class, 'storeManualDeposit'])->name('deposit.manual');
    Route::get('/withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
    Route::post('/withdraw/preview', [WalletController::class, 'withdrawPreview'])->name('withdraw.preview');
    Route::post('/withdraw', [WalletController::class, 'withdrawStore'])->name('withdraw.store');
});

/*
|--------------------------------------------------------------------------
| Ludo tables (server-authoritative; JSON over web session auth)
|--------------------------------------------------------------------------
*/
// Spectator view: public, no auth required to VIEW a match.
Route::get('/play/match/{match}/watch', [PlayController::class, 'watch'])->name('play.watch');

Route::middleware('auth')->prefix('play')->name('play.')->group(function () {
    Route::post('/find', [PlayController::class, 'find'])->name('find');
    Route::get('/match/{match}', [PlayController::class, 'show'])->name('match.show');
    Route::post('/match/{match}/roll', [PlayController::class, 'roll'])->name('match.roll');
    Route::post('/match/{match}/move', [PlayController::class, 'move'])->name('match.move');
    Route::post('/match/{match}/exit', [PlayController::class, 'exit'])->name('match.exit');
    Route::post('/match/{match}/join-invite', [PlayController::class, 'joinInvite'])->name('match.join-invite');
});

/*
|--------------------------------------------------------------------------
| Friends (JSON over web session auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('friends')->name('friends.')->group(function () {
    Route::get('/', [FriendController::class, 'index'])->name('index');
    Route::post('/request', [FriendController::class, 'request'])->name('request');
    Route::post('/{friendship}/accept', [FriendController::class, 'accept'])->name('accept');
    Route::post('/{friendship}/reject', [FriendController::class, 'reject'])->name('reject');
    Route::delete('/{friendship}', [FriendController::class, 'destroy'])->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Tournaments (public standings; auth for join)
|--------------------------------------------------------------------------
*/
// NOTE: the fixture join route must be registered BEFORE
// /tournaments/{tournament}, or "fixtures" would bind as {tournament}.
Route::post('/tournaments/fixtures/{fixture}/join-match', [TournamentController::class, 'joinFixture'])
    ->middleware('auth')->name('tournaments.fixtures.join');
Route::get('/tournaments', [TournamentController::class, 'index'])->name('tournaments.index');
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->name('tournaments.show');
Route::post('/tournaments/{tournament}/join', [TournamentController::class, 'join'])
    ->middleware('auth')->name('tournaments.join');

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

    Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::post('/deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
    Route::post('/deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');

    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/paid', [WithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
    Route::post('/withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');

    Route::get('/tournaments', [AdminTournamentController::class, 'index'])->name('tournaments.index');
    Route::get('/tournaments/create', [AdminTournamentController::class, 'create'])->name('tournaments.create');
    Route::post('/tournaments', [AdminTournamentController::class, 'store'])->name('tournaments.store');
    Route::get('/tournaments/{tournament}', [AdminTournamentController::class, 'show'])->name('tournaments.show');
    Route::delete('/tournaments/{tournament}', [AdminTournamentController::class, 'destroy'])->name('tournaments.destroy');
    Route::post('/tournaments/{tournament}/start-league', [AdminTournamentController::class, 'startLeague'])->name('tournaments.start-league');
    Route::post('/tournaments/{tournament}/advance', [AdminTournamentController::class, 'advance'])->name('tournaments.advance');
    Route::post('/tournaments/{tournament}/complete', [AdminTournamentController::class, 'complete'])->name('tournaments.complete');
});
