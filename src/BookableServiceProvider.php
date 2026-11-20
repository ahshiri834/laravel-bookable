<?php

namespace Ahshiri\Bookable;

use Illuminate\Support\ServiceProvider;

class BookableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bookable.php', 'bookable');
        $this->app->singleton(AvailabilityEngine::class);
        $this->app->singleton(Pricing\PriceCalculator::class);
        $this->app->singleton(BookingManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([Console\ReleaseExpiredCommand::class]);

            $this->publishes([__DIR__.'/../config/bookable.php' => config_path('bookable.php')], 'bookable-config');
        }
    }
}
