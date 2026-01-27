<?php

namespace App\Providers;

use App\Services\Semaphores\SemaphoreFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SemaphoreFactory::class, function ($app) {
            // Get configured class from environment or config
            $className = config('app.semaphore_class');

            if (! $className) {
                throw new \RuntimeException(
                    'Semaphore class is not configured in app.semaphore_class'
                );
            }

            // Pass the class name to factory constructor
            return new SemaphoreFactory($className);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TODO:
        /*
        \View::share('pusherConfig', [
            'key' => config('broadcasting.connections.pusher.key'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
        ]);
        */
    }
}
