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
use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Services\Dispensario\HistoriaClinicaService;
use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Services\Dispensario\RecetaService;
use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Services\Dispensario\AgendaService;
use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Services\Dispensario\InventarioMedicinasService;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Services\InventarioTi\InventarioTiService;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use App\Services\Helpdesk\HelpdeskService;
use App\Contracts\Helpdesk\SlaServiceInterface;
use App\Services\Helpdesk\SlaService;
use App\Contracts\Capacitacion\CapacitacionServiceInterface;
use App\Services\Capacitacion\CapacitacionService;
use App\Contracts\Actividades\ActividadesServiceInterface;
use App\Services\Actividades\ActividadesService;
use App\Contracts\Bienestar\BienestarServiceInterface;
use App\Services\Bienestar\BienestarService;
use App\Contracts\Reporteria\ReporteriaServiceInterface;
use App\Services\Reporteria\ReporteriaService;
use App\Contracts\Handoff\HandoffErpServiceInterface;
use App\Services\Handoff\HandoffErpService;
use App\Contracts\Asistencia\PermisoServiceInterface;
use App\Services\Asistencia\PermisoService;
use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Services\Asistencia\VacacionService;

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
        $this->app->bind(
            HistoriaClinicaServiceInterface::class,
            HistoriaClinicaService::class
        );
        $this->app->bind(
            RecetaServiceInterface::class,
            RecetaService::class
        );
        $this->app->bind(
            AgendaServiceInterface::class,
            AgendaService::class
        );
        $this->app->bind(
            InventarioMedicinasServiceInterface::class,
            InventarioMedicinasService::class
        );
        $this->app->bind(
            InventarioTiServiceInterface::class,
            InventarioTiService::class
        );
        $this->app->bind(
            HelpdeskServiceInterface::class,
            HelpdeskService::class
        );
        $this->app->bind(
            SlaServiceInterface::class,
            SlaService::class
        );
        $this->app->bind(
            CapacitacionServiceInterface::class,
            CapacitacionService::class
        );
        $this->app->bind(
            ActividadesServiceInterface::class,
            ActividadesService::class
        );
        $this->app->bind(
            BienestarServiceInterface::class,
            BienestarService::class
        );
        $this->app->bind(
            ReporteriaServiceInterface::class,
            ReporteriaService::class
        );
        $this->app->bind(
            HandoffErpServiceInterface::class,
            HandoffErpService::class
        );
        $this->app->bind(
            PermisoServiceInterface::class,
            PermisoService::class
        );
        $this->app->bind(
            VacacionServiceInterface::class,
            VacacionService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Expediente\Servidor::observe(\App\Observers\ServidorObserver::class);
    }
}
