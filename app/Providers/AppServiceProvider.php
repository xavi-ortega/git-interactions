<?php

namespace App\Providers;

use App\Helpers\ReportProgressManager;
use App\Observers\ReportProgressObserver;
use App\ReportProgress;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('App\Helpers\ReportProgressManager', function () {
            return new ReportProgressManager();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        ReportProgress::observe(ReportProgressObserver::class);
    }
}
