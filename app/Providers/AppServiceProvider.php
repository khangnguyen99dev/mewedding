<?php

namespace App\Providers;

use App\Services\TemplateRegistry;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TemplateRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make every template's blades resolvable as `templates::{key}.index`.
        View::addNamespace(
            (string) config('templates.view_namespace', 'templates'),
            (string) config('templates.path'),
        );
    }
}
