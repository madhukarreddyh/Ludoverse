<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Ludo\BotStrategy;
use App\Services\Ludo\RandomBotStrategy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bot play style is swappable — Phase 5 can bind a smarter strategy.
        $this->app->bind(BotStrategy::class, RandomBotStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public friend codes ("LV" + zero-padded id) on signup.
        User::observe(UserObserver::class);
    }
}
