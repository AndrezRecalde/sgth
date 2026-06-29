<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Models\User;
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
use App\Contracts\Dispensario\EstadisticasDispensarioServiceInterface;
use App\Services\Dispensario\EstadisticasDispensarioService;
use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Services\Dispensario\AgendaService;
use App\Contracts\Dispensario\PacienteServiceInterface;
use App\Services\Dispensario\PacienteService;
use App\Contracts\Dispensario\DisponibilidadServiceInterface;
use App\Services\Dispensario\DisponibilidadService;
use App\Contracts\Dispensario\AdquisicionServiceInterface;
use App\Services\Dispensario\AdquisicionService;
use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Services\Dispensario\AtencionEnfermeriaService;
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
use App\Contracts\Nomina\NominaServiceInterface;
use App\Services\Nomina\NominaService;
use App\Contracts\Autoservicio\AutoservicioServiceInterface;
use App\Services\Autoservicio\AutoservicioService;
use App\Contracts\Biometrico\BiometricoServiceInterface;
use App\Services\Biometrico\BiometricoService;
use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use App\Services\Disciplinario\DisciplinarioService;
use App\Contracts\Evaluacion\EvaluacionServiceInterface;
use App\Services\Evaluacion\EvaluacionService;
use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Services\Seleccion\SeleccionService;
use App\Contracts\Sgd\SgdServiceInterface;
use App\Services\Sgd\SgdService;

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
            EstadisticasDispensarioServiceInterface::class,
            EstadisticasDispensarioService::class
        );
        $this->app->bind(
            AgendaServiceInterface::class,
            AgendaService::class
        );
        $this->app->bind(
            PacienteServiceInterface::class,
            PacienteService::class
        );
        $this->app->bind(
            DisponibilidadServiceInterface::class,
            DisponibilidadService::class
        );
        $this->app->bind(
            AdquisicionServiceInterface::class,
            AdquisicionService::class
        );
        $this->app->bind(
            AtencionEnfermeriaServiceInterface::class,
            AtencionEnfermeriaService::class
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
        $this->app->bind(
            \App\Contracts\Viatico\ViaticoServiceInterface::class,
            \App\Services\Viatico\ViaticoService::class
        );
        $this->app->bind(
            NominaServiceInterface::class,
            NominaService::class
        );
        $this->app->bind(
            AutoservicioServiceInterface::class,
            AutoservicioService::class
        );
        $this->app->bind(
            BiometricoServiceInterface::class,
            BiometricoService::class
        );
        $this->app->bind(
            DisciplinarioServiceInterface::class,
            DisciplinarioService::class
        );
        $this->app->bind(
            EvaluacionServiceInterface::class,
            EvaluacionService::class
        );
        $this->app->bind(
            SeleccionServiceInterface::class,
            SeleccionService::class
        );
        $this->app->bind(
            SgdServiceInterface::class,
            SgdService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Expediente\Servidor::observe(
            \App\Observers\ServidorObserver::class
        );

        Gate::define('viewPulse', function (?User $user) {
            if (app()->environment('local')) {
                return true;
            }
            return $user?->hasRole('admin-ti');
        });

        Gate::define('viewApiDocs', function (?User $user) {
            if (app()->environment('local')) {
                return true;
            }
            return $user?->hasRole('admin-ti');
        });

        // Super administradores — acceso sin restricciones
        Gate::before(function ($user, $ability) {
            if ($user->hasAnyRole(['admin-ti', 'admin-uath'])) {
                return true;
            }
        });

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
