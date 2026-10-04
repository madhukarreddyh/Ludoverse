<?php

use App\Services\Ludo\MatchService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Ludo match ticker
|--------------------------------------------------------------------------
| Drives the server-authoritative clock: expires match timers, punishes
| missed turns (5 misses = removal), and auto-plays bot turns. Run every
| few seconds (e.g. via the scheduler or a supervisor loop).
*/
Artisan::command('matches:tick', function (MatchService $matches) {
    $matches->tick();
    $this->comment('Tick complete.');
})->purpose('Advance running Ludo matches (timers, missed turns, bots)');
