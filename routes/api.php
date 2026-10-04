<?php

use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\PartnerApiController;
use App\Http\Controllers\Api\PublicApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LudoVerse API (Phase 6)
|--------------------------------------------------------------------------
| Every endpoint below is registered in App\Services\ApiDocRegistry —
| GET /api/docs renders that registry. Keep the two in sync.
*/

/*
 | Public player API. Auth: public API key (Bearer) for player/login,
 | player token (Bearer) for the game endpoints.
 */
Route::prefix('v1/public')->name('api.public.')->group(function () {
    Route::post('/player/login', [PublicApiController::class, 'playerLogin'])
        ->middleware('api.key:public')->name('player.login');

    Route::middleware('player.token')->group(function () {
        Route::get('/wallet/balance', [PublicApiController::class, 'walletBalance'])->name('wallet.balance');
        Route::post('/match/start', [PublicApiController::class, 'matchStart'])->name('match.start');
        Route::post('/match/join', [PublicApiController::class, 'matchJoin'])->name('match.join');
        Route::get('/match/{id}/result', [PublicApiController::class, 'matchResult'])->name('match.result');
    });
});

/*
 | Private partner API. Auth: Bearer PRIVATE key + IP whitelist.
 | Includes tournament entry and direct wallet debit/credit — powerful,
 | keep keys server-side and IP-whitelisted.
 */
Route::prefix('v1/partner')->name('api.partner.')->middleware('api.key:private')->group(function () {
    Route::get('/tournaments', [PartnerApiController::class, 'tournamentList'])->name('tournaments.index');
    Route::post('/tournaments/{id}/join', [PartnerApiController::class, 'tournamentJoin'])->name('tournaments.join');
    Route::post('/wallet/debit', [PartnerApiController::class, 'walletDebit'])->name('wallet.debit');
    Route::post('/wallet/credit', [PartnerApiController::class, 'walletCredit'])->name('wallet.credit');
});

/*
 | Auto-generated docs + the iframe-embedding partner guide.
 */
Route::get('/docs', [DocsController::class, 'index'])->name('api.docs');
Route::get('/docs/embedding', [DocsController::class, 'embedding'])->name('api.docs.embedding');
