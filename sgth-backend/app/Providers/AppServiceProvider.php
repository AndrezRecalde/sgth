<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Auth\AuthServiceInterface;
use App\Services\Auth\AuthService;
use App\Contracts\Admin\UsuarioServiceInterface;
use App\Services\Admin\UsuarioService;
use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Services\Estructura\EstructuraService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AuthServiceInterface::class,
            AuthService::class
        );
        $this->app->bind(
            UsuarioServiceInterface::class,
            UsuarioService::class
        );
        $this->app->bind(
            EstructuraServiceInterface::class,
            EstructuraService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
