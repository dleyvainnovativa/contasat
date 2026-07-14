<?php

namespace App\Providers;

use App\Services\WorkContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkContext::class);

        $this->app->singleton(\Kreait\Firebase\Contract\Auth::class, function () {
            return (new \Kreait\Firebase\Factory)
                ->withServiceAccount(base_path(config('firebase.credentials')))
                ->createAuth();
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Make the working context and Firebase web config available to every view
        // so the top bar (client/period switcher) and login screen can render.
        View::composer('*', function ($view) {
            $view->with('workContext', app(WorkContext::class));
        });

        View::composer(['auth.login', 'layouts.app'], function ($view) {
            $view->with('firebaseConfig', [
                'apiKey'            => config('firebase.web.api_key'),
                'authDomain'        => config('firebase.web.auth_domain'),
                'projectId'         => config('firebase.web.project_id'),
                'appId'             => config('firebase.web.app_id'),
            ]);
        });
    }
}
