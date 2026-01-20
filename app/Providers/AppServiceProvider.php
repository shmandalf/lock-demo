<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    	$this->app->singleton('semaphore', function () {
        	return new \App\Services\SemaphoreManager();
	});
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \View::share('pusherConfig', [
            'key' => config('broadcasting.connections.pusher.key'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
        ]);
    }
}
