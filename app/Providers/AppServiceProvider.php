<?php

namespace App\Providers;

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
        //
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
