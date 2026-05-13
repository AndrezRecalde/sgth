<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Auth\AuthServiceInterface;
use App\Services\Auth\AuthService;
use App\Contracts\Admin\UsuarioServiceInterface;
use App\Services\Admin\UsuarioService;
use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Services\Estructura\EstructuraService;
use App\Contracts\Expediente\SubrogacionServiceInterface;
use App\Services\Expediente\SubrogacionService;
use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Services\Expediente\ExpedienteService;
use App\Contracts\Sso\SsoServiceInterface;
use App\Services\Sso\SsoService;

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
        $this->app->bind(
            SubrogacionServiceInterface::class,
            SubrogacionService::class
        );
        $this->app->bind(
            ExpedienteServiceInterface::class,
            ExpedienteService::class
        );
        $this->app->bind(
            SsoServiceInterface::class,
            SsoService::class
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
