<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In local/testing: surface N+1 queries and discarded fillable attributes
        // immediately rather than silently failing in production.
        if ($this->app->isLocal() || $this->app->runningUnitTests()) {
            Model::preventLazyLoading();
            Model::preventSilentlyDiscardingAttributes();
        }
    }
}
