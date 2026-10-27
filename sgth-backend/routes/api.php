<?php

use App\Http\Controllers\Admin\PermisoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Asistencia\ConsolidadoPermisoController;
use App\Http\Controllers\Asistencia\MarcacionController;
use App\Http\Controllers\Asistencia\PeriodoVacacionController;
use App\Http\Controllers\Asistencia\PermisoServidorController;
use App\Http\Controllers\Asistencia\PermisoSirha7Controller;
use App\Http\Controllers\Asistencia\CertificadoAprobacionController;
use App\Http\Controllers\Asistencia\LicenciaController;
use App\Http\Controllers\Asistencia\VacacionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Autoservicio\AutoservicioController;
use App\Http\Controllers\Autoservicio\MisActividadesController;
use App\Http\Controllers\Autoservicio\MiCredencialController;
use App\Http\Controllers\Autoservicio\MisComunicadosController;
use App\Http\Controllers\Autoservicio\MiFormacionController;
use App\Http\Controllers\Autoservicio\MiPerfilController;
use App\Http\Controllers\Autoservicio\MisEquiposController;
use App\Http\Controllers\Autoservicio\MisPrestamosController;
use App\Http\Controllers\Autoservicio\MisCargasFamiliaresController;
use App\Http\Controllers\Autoservicio\MisCertificadosMedicosController;
use App\Http\Controllers\Autoservicio\MisDocumentosLaboralesController;
use App\Http\Controllers\Autoservicio\MisSolicitudesAccionController;
use App\Http\Controllers\Bienestar\EncuestaClimaController;
use App\Http\Controllers\Bienestar\PlanBienestarController;
use App\Http\Controllers\Capacitacion\CertificadoCapacitacionController;
use App\Http\Controllers\Capacitacion\CursoController;
use App\Http\Controllers\Capacitacion\PlanCapacitacionController;
use App\Http\Controllers\Catalogo\CantonController;
use App\Http\Controllers\Catalogo\EntidadFinancieraController;
use App\Http\Controllers\Catalogo\ProvinciaController;
use App\Http\Controllers\Catalogo\TipoUnidadController;
use App\Http\Controllers\Disciplinario\DisciplinarioController;
use App\Http\Controllers\Disciplinario\FaltaReglamentoInternoController;
use App\Http\Controllers\Disciplinario\VistoBuenoController;
use App\Http\Controllers\Dispensario\AdquisicionController;
use App\Http\Controllers\Dispensario\TableroSaludOcupacionalController;
use App\Http\Controllers\Dispensario\MiJornadaController;
use App\Http\Controllers\Dispensario\ReporteDispensarioController;
use App\Http\Controllers\Dispensario\AgendaController;
use App\Http\Controllers\Dispensario\AlergiaPacienteController;
use App\Http\Controllers\Dispensario\AntecedentePacienteController;
use App\Http\Controllers\Dispensario\AtencionEnfermeriaController;
use App\Http\Controllers\Dispensario\CatalogoExamenController;
use App\Http\Controllers\Dispensario\PerfilExamenController;
use App\Http\Controllers\Dispensario\BandejaExamenesController;
use App\Http\Controllers\Dispensario\OrdenExamenController;
use App\Http\Controllers\Dispensario\CertificadoMedicoController;
use App\Http\Controllers\Dispensario\BorradorConsultaController;
use App\Http\Controllers\Dispensario\ConsultaMedicaController;
use App\Http\Controllers\Dispensario\DashboardDispensarioController;
use App\Http\Controllers\Dispensario\DiagnosticoCie10Controller;
use App\Http\Controllers\Dispensario\DisponibilidadController;
use App\Http\Controllers\Dispensario\CertificadoAptitudController;
use App\Http\Controllers\Dispensario\CoberturaCertificacionController;
use App\Http\Controllers\Dispensario\FemoPdfController;
use App\Http\Controllers\Dispensario\ExamenesPorRiesgoController;
use App\Http\Controllers\Dispensario\FemoResultadosController;
use App\Http\Controllers\Dispensario\FichaSaludOcupacionalController;
use App\Http\Controllers\Dispensario\HistoriaClinicaController;
use App\Http\Controllers\Dispensario\InventarioMedicinasController;
use App\Http\Controllers\Dispensario\ItemRecetaController;
use App\Http\Controllers\Dispensario\OdontogramaController;
use App\Http\Controllers\Dispensario\PacienteController;
use App\Http\Controllers\Dispensario\PersonalMedicoController;
use App\Http\Controllers\Dispensario\RecetaController;
use App\Http\Controllers\Dispensario\ResultadoMedicoController;
use App\Http\Controllers\Dispensario\RevisionResultadoController;
use App\Http\Controllers\Dispensario\SolicitudCertificacionController;
use App\Http\Controllers\Dispensario\TriajeController;
use App\Http\Controllers\Estructura\CargoController;
use App\Http\Controllers\Estructura\ExtensionTelefonicaController;
use App\Http\Controllers\Estructura\EscalaRemuneracionController;
use App\Http\Controllers\Estructura\GrupoOcupacionalController;
use App\Http\Controllers\Estructura\OrganigramaController;
use App\Http\Controllers\Estructura\OrganigramaPdfController;
use App\Http\Controllers\Estructura\PartidaPresupuestariaController;
use App\Http\Controllers\Estructura\PuestoActividadController;
use App\Http\Controllers\Estructura\PlantillaController;
use App\Http\Controllers\Estructura\PuestoController;
use App\Http\Controllers\Estructura\UnidadAdministrativaController;
use App\Http\Controllers\Evaluacion\EvaluacionController;
use App\Http\Controllers\Expediente\CargaFamiliarController;
use App\Http\Controllers\Expediente\AccionPersonalPdfController;
use App\Http\Controllers\Expediente\AusentismoSaludController;
use App\Http\Controllers\Expediente\CertificadoLaboralController;
use App\Http\Controllers\Expediente\FirmanteAccionPersonalController;
use App\Http\Controllers\Expediente\BuscarServidorController;
use App\Http\Controllers\Expediente\ExportServidoresController;
use App\Http\Controllers\Expediente\ContratoServidorController;
use App\Http\Controllers\Expediente\CuentaBancariaServidorController;
use App\Http\Controllers\Expediente\DeclaracionJuramentadaController;
use App\Http\Controllers\Expediente\DiscapacidadCargaFamiliarController;
use App\Http\Controllers\Expediente\DiscapacidadServidorController;
use App\Http\Controllers\Expediente\DocumentoServidorController;
use App\Http\Controllers\Expediente\EnfermedadCargaFamiliarController;
use App\Http\Controllers\Expediente\EnfermedadCatastroficaServidorController;
use App\Http\Controllers\Expediente\HistorialAcademicoController;
use App\Http\Controllers\Expediente\AusenciaTemporalController;
use App\Http\Controllers\Expediente\CatalogoAccionesPersonalController;
use App\Http\Controllers\Expediente\MovimientoPersonalController;
use App\Http\Controllers\Expediente\AutoridadElectaController;
use App\Http\Controllers\Expediente\TerminacionObreroController;
use App\Http\Controllers\Expediente\ContratoColectivoController;
use App\Http\Controllers\Expediente\SolicitudAccionPersonalController;
use App\Http\Controllers\Expediente\SolicitudCargaFamiliarController;
use App\Http\Controllers\Expediente\DesvinculacionController;
use App\Http\Controllers\Expediente\LoteAccionController;
use App\Http\Controllers\Expediente\PeriodoDePruebaController;
use App\Http\Controllers\Expediente\VerificacionCertificadoController;
use App\Http\Controllers\Expediente\VerificacionCredencialController;
use App\Http\Controllers\Expediente\VinculacionInicialController;
use App\Http\Controllers\Expediente\ServidorController;
use App\Http\Controllers\Expediente\SubrogacionController;
use App\Http\Controllers\Helpdesk\CategoriaTicketController;
use App\Http\Controllers\Helpdesk\BaseConocimientoController;
use App\Http\Controllers\Helpdesk\ComentarioTicketController;
use App\Http\Controllers\Helpdesk\EncuestaSatisfaccionController;
use App\Http\Controllers\Helpdesk\SlaController;
use App\Http\Controllers\Helpdesk\TecnicoDticController;
use App\Http\Controllers\Tic\ReporteTicController;
use App\Http\Controllers\Tic\TableroTicController;
use App\Http\Controllers\Helpdesk\TicketController;
use App\Http\Controllers\InventarioTi\AsignacionBienController;
use App\Http\Controllers\InventarioTi\CampaniaMantenimientoController;
use App\Http\Controllers\InventarioTi\ConsumibleController;
use App\Http\Controllers\InventarioTi\MovimientoConsumibleController;
use App\Http\Controllers\InventarioTi\SolicitudBodegaController;
use App\Http\Controllers\Comunicacion\CargaFotografiasController;
use App\Http\Controllers\Comunicacion\CredencialController;
use App\Http\Controllers\Comunicacion\TableroComunicacionController;
use App\Http\Controllers\Comunicacion\FotografiaServidorController;
use App\Http\Controllers\Comunicacion\IdentidadInstitucionalController;
use App\Http\Controllers\Comunicacion\IdentidadPublicaController;
use App\Http\Controllers\Comunicados\ArchivosComunicadoController;
use App\Http\Controllers\Comunicados\ComunicadoController;
use App\Http\Controllers\Comunicados\SeguimientoComunicadoController;
use App\Http\Controllers\InventarioTi\ConstatacionController;
use App\Http\Controllers\InventarioTi\BajaController;
use App\Http\Controllers\InventarioTi\BienInformaticoController;
use App\Http\Controllers\InventarioTi\CatalogoInventarioController;
use App\Http\Controllers\InventarioTi\EtiquetaBienController;
use App\Http\Controllers\InventarioTi\ContratoVigenciaController;
use App\Http\Controllers\InventarioTi\MantenimientoBienController;
use App\Http\Controllers\InventarioTi\PrestamoEquipoController;
use App\Http\Controllers\InventarioTi\ReglasPrestamoController;
use App\Http\Controllers\InventarioTi\ReporteUsoPrestamoController;
use App\Http\Controllers\InventarioTi\UbicacionesBienController;
use App\Http\Controllers\Nomina\NovedadNominaController;
use App\Http\Controllers\Reporte\ReporteSiithSutController;
use App\Http\Controllers\Reporteria\DashboardController;
use App\Http\Controllers\Reporteria\ReporteController;
use App\Http\Controllers\Seleccion\CalificacionController;
use App\Http\Controllers\Seleccion\ContenedorExpressController;
use App\Http\Controllers\Seleccion\OnboardingController;
use App\Http\Controllers\Seleccion\ConvocatoriaController;
use App\Http\Controllers\Seleccion\CriterioEvaluacionController;
use App\Http\Controllers\Seleccion\PlantillaEvaluacionController;
use App\Http\Controllers\Seleccion\PostulanteController;
use App\Http\Controllers\Seleccion\SeleccionController;
use App\Http\Controllers\Sgd\DocumentoInstitucionalController;
use App\Http\Controllers\Sso\AccidenteTrabajoController;
use App\Http\Controllers\Sso\CapacitacionSsoController;
use App\Http\Controllers\Sso\CumplimientoNormativaController;
use App\Http\Controllers\Sso\DashboardSsoController;
use App\Http\Controllers\Sso\EppEntregaController;
use App\Http\Controllers\Sso\EquipoProteccionController;
use App\Http\Controllers\Sso\FactorRiesgoCatalogoController;
use App\Http\Controllers\Sso\DocumentoSsoController;
use App\Http\Controllers\Sso\EvaluacionAssistController;
use App\Http\Controllers\Sso\HorasTrabajadasController;
use App\Http\Controllers\Sso\IndicadoresSsoController;
use App\Http\Controllers\Sso\InspeccionSsoController;
use App\Http\Controllers\Sso\EvaluacionPsicosocialController;
use App\Http\Controllers\Sso\NormativaLegalSsoController;
use App\Http\Controllers\Sso\ProgramaDrogaActividadController;
use App\Http\Controllers\Sso\ProgramaDrogaSeguimientoController;
use App\Http\Controllers\Sso\PuestoEppController;
use App\Http\Controllers\Sso\RespuestaAssistController;
use App\Http\Controllers\Sso\RespuestaPsicosocialController;
use App\Http\Controllers\Sso\RiesgoLaboralController;
use App\Http\Controllers\Viatico\AutorizacionVueloController;
use App\Http\Controllers\Viatico\BandejaViaticoController;
use App\Http\Controllers\Viatico\CatalogoViaticoController;
use App\Http\Controllers\Viatico\InformeViaticoController;
use App\Http\Controllers\Viatico\LiquidacionViaticoController;
use App\Http\Controllers\Viatico\TramoViaticoController;
use App\Http\Controllers\Viatico\ViaticoController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // Sin `throttle` de ruta: contaba también los aciertos por IP, y con
        // toda la institución detrás de una sola dejaba fuera a quien entrara
        // sexto en el mismo minuto. Los fallos se limitan en AuthService.
        Route::post('login', [AuthController::class, 'login']);
    });

    // El QR del permiso impreso ya no abre nada público: lo escanea Talento
    // Humano para confirmar o rechazar el documento, así que lleva a la
    // pantalla autenticada y la consulta por folio vive dentro de la sesión
    // (`asistencia/permisos/folio/{folio}`). La verificación anónima que había
    // aquí servía a un caso —el guardia comprobando que el papel es auténtico—
    // que no existe en la institución, y exponía folios correlativos a quien
    // quisiera recorrerlos.

    // Endpoint público protegido criptográficamente mediante firmas temporales (URL firmada)
    Route::get('sgd/documentos/{documento}/descargar', [DocumentoInstitucionalController::class, 'descargar'])
        ->name('sgd.documentos.descargar');

    // El organigrama es información pública: se consulta sin sesión desde la
    // página abierta a la ciudadanía. Con un token de alguien que puede ver
    // estructura, el mismo endpoint amplía el detalle (puestos, subrogaciones);
    // sin él devuelve solo el árbol de unidades y subprocesos.
    Route::get('estructura/organigrama', OrganigramaController::class);
    Route::get('estructura/organigrama/pdf', OrganigramaPdfController::class)
        ->name('estructura.organigrama.pdf');

    // Catálogos geográficos públicos
    Route::get('catalogos/provincias', [ProvinciaController::class, 'index']);
    Route::get('catalogos/provincias/{id}/cantones', [CantonController::class, 'porProvincia']);

    // SSO: cuestionario de evaluación de riesgo psicosocial — anónimo por diseño (sin
    // login de servidor), accesible únicamente por código de campaña.
    Route::get('sso/psicosocial/{codigo}/cuestionario', [RespuestaPsicosocialController::class, 'cuestionario']);
    Route::post('sso/psicosocial/{codigo}/respuestas', [RespuestaPsicosocialController::class, 'store'])
        ->middleware('throttle:20,1');

    // SSO: tamizaje ASSIST del programa de prevención de drogas (Fase 4, Instructivo
    // MDT-MSP-2019-038) — anónimo y confidencial por mandato regulatorio, sin login de servidor.
    Route::get('sso/assist/{codigo}/cuestionario', [RespuestaAssistController::class, 'cuestionario']);
    Route::post('sso/assist/{codigo}/respuestas', [RespuestaAssistController::class, 'store'])
        ->middleware('throttle:20,1');

    // Comprobar que un certificado laboral salió de aquí. La usan el banco o
    // el IESS que reciben el papel, así que no hay sesión que pedirles.
    //
    // Es la misma superficie que tuvo la verificación de permisos y que se
    // retiró más arriba por exponer folios correlativos. Aquí no se repite:
    // el código es aleatorio (ver CertificadoLaboralService) y la ruta va
    // limitada por IP, para que recorrerla no compense.
    Route::get('certificados/verificar/{codigo}', VerificacionCertificadoController::class)
        ->middleware('throttle:10,1')
        ->name('certificados.verificar');

    // La credencial del servidor, desde el QR de su reverso (Comunicación
    // Social, 6.4). Igual que el certificado: código aleatorio de 40
    // caracteres y límite por IP. Si no está vigente, no dice de quién era.
    Route::get('credenciales/verificar/{codigo}', VerificacionCredencialController::class)
        ->where('codigo', '[A-Za-z0-9]{32,64}')
        ->middleware('throttle:10,1')
        ->name('credenciales.verificar');

    // Los logos vigentes y los datos de la institución, sin sesión: los piden
    // el login, la verificación pública y los correos (Comunicación Social,
    // fase 2.1). No son reservados: salen en cualquier documento. Una versión
    // programada no se sirve hasta que rige.
    Route::prefix('identidad')->middleware('throttle:120,1')->group(function () {
        Route::get('/', [IdentidadPublicaController::class, 'manifiesto']);
        Route::get('icono/{lado}', [IdentidadPublicaController::class, 'icono'])
            ->whereIn('lado', ['32', '180', '192', '512']);
        Route::get('{variante}', [IdentidadPublicaController::class, 'variante']);
    });

    // SSO: descarga de documentos adjuntos (Fase 9) protegida por firma temporal de URL,
    // igual que sgd.documentos.descargar.
    Route::get('sso/documentos/{documento}/descargar', [DocumentoSsoController::class, 'descargar'])
        ->name('sso.documentos.descargar');
});

// ── Rutas autenticadas ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'usuario-activo', 'primer-login'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('perfil', [AuthController::class, 'perfil']);

        // Esta ruta debe ignorar la restricción del primer-login (manejado internamente por el middleware)
        Route::post('cambiar-contrasena',
            [AuthController::class, 'cambiarContrasenaInicial']
        );

        // Cambio voluntario: pide la contraseña actual. El límite es por
        // usuario y frena a quien, con una sesión ajena abierta, intente
        // adivinarla.
        Route::put('contrasena', [PasswordController::class, 'actualizar'])
            ->middleware(['permission:cambiar-contrasena', 'throttle:5,1']);
    });

    // Rutas públicas de catálogo
    Route::get('catalogos/entidades-financieras', [EntidadFinancieraController::class, 'index']);
    Route::get('catalogos/tipos-unidad', [TipoUnidadController::class, 'index']);

    // Rutas de administración. Los bancos de las cuentas de los servidores los
    // lleva Talento Humano; admin-ti salió de aquí al dejar de ser
    // superusuario (docs/plan-roles-y-accesos.md, PR 3).
    Route::apiResource('catalogos/entidades-financieras', EntidadFinancieraController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin-uath');

    // Módulo 01: Estructura Organizacional
    Route::prefix('estructura')->group(function () {
        // `organigrama` y `organigrama/pdf` viven en el grupo público de más
        // arriba: leer la estructura de la institución no exige sesión.

        // Estado de la plantilla: plazas, ocupación y personal por modalidad.
        Route::get('plantilla', [PlantillaController::class, 'resumen'])
            ->name('estructura.plantilla');
        Route::get(
            'unidades-administrativas/todas',
            [UnidadAdministrativaController::class, 'todas']
        );
        // Antes del apiResource: si no, `{unidades_administrativa}` se come la
        // ruta y Laravel busca una unidad con id «sugerir-codigo».
        Route::get(
            'unidades-administrativas/sugerir-codigo',
            [UnidadAdministrativaController::class, 'sugerirCodigo']
        );
        Route::apiResource('unidades-administrativas', UnidadAdministrativaController::class);
        Route::apiResource('puestos', PuestoController::class);
        Route::prefix('puestos/{puestoId}/actividades')
            ->group(function () {
                Route::get('/', [PuestoActividadController::class, 'index']);
                Route::post('/', [PuestoActividadController::class, 'store']);
                Route::patch('{actividadId}', [PuestoActividadController::class, 'update']);
                Route::delete('{actividadId}', [PuestoActividadController::class, 'destroy']);
                Route::post('reordenar', [PuestoActividadController::class, 'reordenar']);
            });

        Route::get('cargos', [CargoController::class, 'index'])
            ->name('estructura.cargos.index');
        Route::post('cargos', [CargoController::class, 'store'])
            ->name('estructura.cargos.store');
        Route::put('cargos/{id}', [CargoController::class, 'update'])
            ->name('estructura.cargos.update');
        Route::delete('cargos/{id}', [CargoController::class, 'destroy'])
            ->name('estructura.cargos.destroy');

        Route::get('grupos-ocupacionales', [GrupoOcupacionalController::class, 'index'])
            ->name('estructura.grupos-ocupacionales');

        // La nueva escala de remuneraciones de la LOSEP (fase 7.3b): la prepara
        // quien prepara acciones; la registra y la revierte el Director.
        Route::get('escalas-remuneracion', [EscalaRemuneracionController::class, 'index'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');
        Route::post('escalas-remuneracion/previsualizar', [EscalaRemuneracionController::class, 'previsualizar'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal')
            ->name('estructura.escalas-remuneracion.previsualizar');
        Route::post('escalas-remuneracion', [EscalaRemuneracionController::class, 'store'])
            ->middleware('permission:registrar-accion-personal');
        Route::post('escalas-remuneracion/{escala}/revertir', [EscalaRemuneracionController::class, 'revertir'])
            ->middleware('permission:anular-accion-personal');

        // Catálogo de partidas presupuestarias. La lectura queda abierta a
        // cualquier usuario autenticado porque alimenta los selectores de
        // Puestos y de las acciones de personal; la gestión del catálogo la
        // restringe el middleware de rol, igual que las extensiones.
        Route::get('partidas-presupuestarias', [PartidaPresupuestariaController::class, 'index'])
            ->name('estructura.partidas-presupuestarias.index');
        Route::get('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'show'])
            ->name('estructura.partidas-presupuestarias.show');

        // admin-ti salió de aquí el 2026-10-26: las partidas son de Talento
        // Humano (docs/plan-roles-y-accesos.md, PR 3).
        Route::middleware('role:admin-uath')->group(function () {
            Route::post('partidas-presupuestarias', [PartidaPresupuestariaController::class, 'store'])
                ->name('estructura.partidas-presupuestarias.store');
            Route::put('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'update'])
                ->name('estructura.partidas-presupuestarias.update');
            Route::delete('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'destroy'])
                ->name('estructura.partidas-presupuestarias.destroy');
        });

        // El directorio telefónico: lo consulta todo el personal desde el
        // Portal; las extensiones las mantiene TIC, también las inactivas
        // (docs/plan-roles-y-accesos.md, PR 11).
        Route::get('directorio-telefonico', [ExtensionTelefonicaController::class, 'index']);

        Route::middleware('permission:gestionar-directorio')->group(function () {
            Route::post('extensiones', [ExtensionTelefonicaController::class, 'store']);
            Route::put('extensiones/{id}', [ExtensionTelefonicaController::class, 'update']);
            Route::delete('extensiones/{id}', [ExtensionTelefonicaController::class, 'destroy']);
        });
    });

    // Módulo 02: Expediente Digital
    Route::get('expediente/servidores/sin-usuario',
        [ServidorController::class, 'sinUsuario'])
        ->name('servidores.sinUsuario');

    Route::post('expediente/servidores/basico',
        [ServidorController::class, 'storeBasico'])
        ->name('servidores.storeBasico');

    // Condiciones de salud de una carga familiar. Las mismas manos que
    // gestionan la carga (ver el grupo de historiales, más abajo): hasta el
    // 2026-09-19 no pedían rol y cualquier usuario autenticado podía
    // agregarlas o borrarlas en el expediente de otro.
    Route::prefix('expediente/cargas-familiares/{cargaId}')
        ->middleware('role:admin-uath|asistente-uath')
        ->group(function () {
            Route::post('discapacidades', [DiscapacidadCargaFamiliarController::class, 'store'])
                ->name('carga.discapacidades.store');
            Route::put('discapacidades/{id}', [DiscapacidadCargaFamiliarController::class, 'update'])
                ->name('carga.discapacidades.update');
            Route::delete('discapacidades/{id}', [DiscapacidadCargaFamiliarController::class, 'destroy'])
                ->name('carga.discapacidades.destroy');
            Route::post('enfermedades', [EnfermedadCargaFamiliarController::class, 'store'])
                ->name('carga.enfermedades.store');
            Route::put('enfermedades/{id}', [EnfermedadCargaFamiliarController::class, 'update'])
                ->name('carga.enfermedades.update');
            Route::delete('enfermedades/{id}', [EnfermedadCargaFamiliarController::class, 'destroy'])
                ->name('carga.enfermedades.destroy');
        });

    Route::prefix('expediente')->group(function () {
        // El buscador de los formularios: elegir a una persona para un ticket,
        // una custodia o un filtro. Con su propio permiso y no con
        // ServidorPolicy, que solo sirve el listado a Talento Humano: TIC
        // recibía 403 (docs/plan-roles-y-accesos.md, PR 2). Antes que
        // `apiResource`, o `servidores/{servidore}` se traga la ruta literal.
        Route::middleware('permission:buscar-servidores')->group(function () {
            Route::get('servidores/buscar', [BuscarServidorController::class, 'index'])
                ->name('servidores.buscar');
            Route::get('servidores/buscar/{id}', [BuscarServidorController::class, 'show'])
                ->whereNumber('id')
                ->name('servidores.buscar.show');
        });

        Route::get('servidores-export/excel', [ExportServidoresController::class, 'excel']);
        Route::get('servidores-export/pdf', [ExportServidoresController::class, 'pdf']);
        // 'store' retirado: sin consumidor real, y su contrato de datos
        // (puesto/unidad/tipo_nombramiento requeridos al crear) contradice
        // la materialización tardía vía creaVinculo(). La creación real
        // pasa por 'basico' + un MovimientoPersonal de ingreso.
        // 'destroy' tampoco: el controlador nunca lo implementó y la ruta
        // respondía 500. Una ficha no se borra; el servidor que sale queda
        // inactivo con su vínculo cerrado.
        Route::apiResource('servidores', ServidorController::class)->except(['store', 'destroy']);

        // Documentos del expediente. Leer y descargar se autorizan por
        // ServidorPolicy::ver —el titular ve los suyos, Talento Humano los de
        // todos—, pero SUBIR y BORRAR piden rol explícito.
        //
        // Hasta el 2026-09-27 la escritura no tenía middleware y se autorizaba
        // solo con ServidorPolicy::actualizar, que también deja pasar al propio
        // titular: cualquiera podía meter o quitar papeles de su propio
        // expediente por API, aunque ninguna pantalla lo ofrezca. Al ampliar ese
        // policy a 'asistente-uath' —para que pudiera trabajar las acciones de
        // personal— eso se habría extendido al expediente de cualquiera, así que
        // la escritura se ancla aquí: quien archiva un documento en el
        // expediente es Talento Humano.
        //
        // Desde el 2026-10-03 el asistente también SUBE (decisión de Talento
        // Humano, «por el momento», pendiente de reforzar): veía el botón y
        // recibía 403, aunque en Declaraciones sí podía subir. BORRAR sigue
        // siendo solo de admin-uath.
        Route::prefix('servidores/{servidorId}')->group(function () {
            Route::get('documentos',
                [DocumentoServidorController::class, 'index'])
                ->name('documentos.index');
            Route::post('documentos',
                [DocumentoServidorController::class, 'store'])
                ->middleware('role:admin-uath|asistente-uath')
                ->name('documentos.store');
            Route::delete('documentos/{documentoId}',
                [DocumentoServidorController::class, 'destroy'])
                ->middleware('role:admin-uath')
                ->name('documentos.destroy');
            Route::get('documentos/{documentoId}/descargar',
                [DocumentoServidorController::class, 'descargar'])
                ->name('documentos.descargar');
        });
        // Acciones de personal: cada paso del trámite con su permiso (diseño,
        // 6.3), no con el rol. Hasta la fase 1.3 el asistente suscribía,
        // registraba y anulaba como el director.
        Route::get('servidores/{servidor}/movimientos', [MovimientoPersonalController::class, 'index']);
        Route::post('servidores/{servidorId}/movimientos', [MovimientoPersonalController::class, 'store'])
            ->middleware('permission:preparar-accion-personal');
        Route::get('movimientos', [MovimientoPersonalController::class, 'bandeja'])
            ->middleware('permission:preparar-accion-personal|suscribir-accion-personal|registrar-accion-personal|notificar-accion-personal|anular-accion-personal');
        // Qué acciones existen y qué pide cada una. Lo leen la bandeja y el
        // formulario de «Nueva acción de personal», nadie más.
        Route::get('acciones-personal/catalogo', CatalogoAccionesPersonalController::class)
            ->middleware('permission:preparar-accion-personal|suscribir-accion-personal|registrar-accion-personal|notificar-accion-personal|anular-accion-personal');

        // Quién está temporalmente fuera (comisión de servicios, licencia sin
        // remuneración) y qué hueco falta cubrir con personal de apoyo.
        Route::get('ausencias-temporales', [AusenciaTemporalController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');

        // Quién está en período de prueba (LOSEP 17 b.5; fase 3.2), y su
        // nombramiento definitivo al cumplir los tres meses.
        Route::get('periodos-de-prueba', [PeriodoDePruebaController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('contratos/{contrato}/nombramiento-definitivo', [PeriodoDePruebaController::class, 'nombramientoDefinitivo'])
            ->middleware('permission:preparar-accion-personal');
        // Las autoridades electas en funciones y el fin de su período (fase 4.1).
        Route::get('autoridades-electas', [AutoridadElectaController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::get('autoridades-electas/entrantes', [AutoridadElectaController::class, 'entrantes'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('contratos/{contrato}/fin-del-periodo', [AutoridadElectaController::class, 'fijarFinDelPeriodo'])
            ->middleware('permission:preparar-accion-personal');
        // La subrogación por ley del Viceprefecto (fase 4.1b).
        Route::post('autoridades-electas/subrogacion-por-ley', [AutoridadElectaController::class, 'subrogacionPorLey'])
            ->middleware('permission:preparar-accion-personal');

        // Las terminaciones de obreros y sus actas de finiquito (fase 4.5).
        Route::get('terminaciones-obreros', [TerminacionObreroController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('movimientos/{movimiento}/acta-finiquito', [TerminacionObreroController::class, 'guardarActa'])
            ->middleware('permission:preparar-accion-personal');
        Route::get('movimientos/{movimiento}/acta-finiquito/documento', [TerminacionObreroController::class, 'descargarActa'])
            ->middleware('role:admin-uath|asistente-uath');

        // El contrato colectivo de los obreros y el salario básico (fase 4.6):
        // lo consulta Talento Humano y lo carga su director.
        Route::get('contratos-colectivos', [ContratoColectivoController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('contratos-colectivos', [ContratoColectivoController::class, 'store'])
            ->middleware('role:admin-uath');
        Route::post('contratos-colectivos/{contratoColectivo}', [ContratoColectivoController::class, 'update'])
            ->middleware('role:admin-uath');
        Route::get('contratos-colectivos/{contratoColectivo}/documento', [ContratoColectivoController::class, 'descargar'])
            ->middleware('role:admin-uath|asistente-uath');
        // Sus cláusulas de licencia (fase 5.3).
        Route::post('contratos-colectivos/{contratoColectivo}/licencias', [ContratoColectivoController::class, 'guardarClausulaDeLicencia'])
            ->middleware('role:admin-uath');
        Route::put('contratos-colectivos/{contratoColectivo}/licencias/{clausula}', [ContratoColectivoController::class, 'actualizarClausulaDeLicencia'])
            ->middleware('role:admin-uath');
        Route::delete('contratos-colectivos/{contratoColectivo}/licencias/{clausula}', [ContratoColectivoController::class, 'eliminarClausulaDeLicencia'])
            ->middleware('role:admin-uath');
        Route::post('salarios-basicos', [ContratoColectivoController::class, 'guardarSalarioBasico'])
            ->middleware('role:admin-uath');

        // La desvinculación de quien sale (diseño, 9.9; fase 7.4a): la ve y la
        // gestiona Talento Humano. La liquidación y la indemnización, además,
        // las marca el Director (lo comprueba el servicio).
        Route::get('desvinculaciones', [DesvinculacionController::class, 'index'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');
        Route::get('desvinculaciones/{desvinculacion}', [DesvinculacionController::class, 'show'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');
        Route::post('desvinculaciones/{desvinculacion}/pasos/{paso}', [DesvinculacionController::class, 'marcar'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');
        Route::post('desvinculaciones/{desvinculacion}/bienes/{asignacion}/devolver', [DesvinculacionController::class, 'devolverBien'])
            ->whereNumber('asignacion')
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');
        // La liquidación y la indemnización, del Director (fase 7.4b); la
        // devolución de la indemnización la registra Talento Humano.
        Route::post('desvinculaciones/{desvinculacion}/vacaciones/confirmar', [DesvinculacionController::class, 'confirmarLiquidacion'])
            ->middleware('permission:registrar-accion-personal');
        Route::post('desvinculaciones/{desvinculacion}/indemnizacion', [DesvinculacionController::class, 'registrarIndemnizacion'])
            ->middleware('permission:registrar-accion-personal');
        Route::post('desvinculaciones/{desvinculacion}/indemnizacion/devolucion', [DesvinculacionController::class, 'registrarDevolucion'])
            ->middleware('permission:preparar-accion-personal|registrar-accion-personal');

        // El incremento de los obreros en lote (fase 4.6). Prepararlo es
        // preparar acciones; registrarlo pide suscribir y registrar, como acto
        // por acto; descartarlo, anular.
        Route::get('lotes-accion', [LoteAccionController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('lotes-accion/incremento-obreros/previsualizar', [LoteAccionController::class, 'previsualizar'])
            ->middleware('permission:preparar-accion-personal');
        Route::post('lotes-accion/incremento-obreros', [LoteAccionController::class, 'store'])
            ->middleware('permission:preparar-accion-personal');
        // El traspaso de un puesto a otra unidad (fase 7.1a): lo prepara quien
        // prepara acciones; revertirlo es anular.
        Route::post('lotes-accion/traspaso-puesto/previsualizar', [LoteAccionController::class, 'previsualizarTraspaso'])
            ->middleware('permission:preparar-accion-personal');
        Route::post('lotes-accion/traspaso-puesto', [LoteAccionController::class, 'traspasar'])
            ->middleware('permission:preparar-accion-personal');
        // La revisión de la clasificación de puestos ocupados (fase 7.1b).
        Route::post('lotes-accion/revision-clasificacion/previsualizar', [LoteAccionController::class, 'previsualizarRevision'])
            ->middleware('permission:preparar-accion-personal');
        Route::post('lotes-accion/revision-clasificacion', [LoteAccionController::class, 'revisar'])
            ->middleware('permission:preparar-accion-personal');
        Route::get('lotes-accion/{lote}', [LoteAccionController::class, 'show'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('lotes-accion/{lote}/registrar', [LoteAccionController::class, 'registrar'])
            ->middleware(['permission:suscribir-accion-personal', 'permission:registrar-accion-personal']);
        Route::post('lotes-accion/{lote}/descartar', [LoteAccionController::class, 'descartar'])
            ->middleware('permission:anular-accion-personal');
        Route::post('lotes-accion/{lote}/revertir', [LoteAccionController::class, 'revertir'])
            ->middleware('permission:anular-accion-personal');
        // Operar el lote como conjunto (fase 7.2): sus documentos en un ZIP, y
        // la notificación por el portal de lo registrado.
        Route::get('lotes-accion/{lote}/documentos', [LoteAccionController::class, 'documentos'])
            ->middleware('permission:preparar-accion-personal');
        Route::post('lotes-accion/{lote}/notificar', [LoteAccionController::class, 'notificar'])
            ->middleware('permission:notificar-accion-personal');

        // El ascendido que no superó la prueba vuelve a su puesto (fase 3.3).
        Route::post('contratos/{contrato}/reintegro-del-ascenso', [PeriodoDePruebaController::class, 'reintegroDelAscenso'])
            ->middleware('permission:preparar-accion-personal');

        // Carga inicial: servidores que ya estaban vinculados antes de que el
        // sistema existiera. Se salta la Acción de Personal a propósito, así
        // que va detrás de un permiso propio —no de un rol— para poder
        // revocarlo al terminar la migración sin tocar nada más.
        Route::prefix('vinculacion-inicial')
            ->middleware('permission:vincular-servidor-inicial')
            ->group(function () {
                Route::post('/', [VinculacionInicialController::class, 'store'])
                    ->name('vinculacionInicial.store');
                Route::get('/', [VinculacionInicialController::class, 'index'])
                    ->name('vinculacionInicial.index');
            });

        // Quién firma las acciones de personal, derivado del organigrama. Solo
        // lectura: para cambiar un firmante se cambia el organigrama, no una
        // designación paralela.
        Route::get('firmantes-accion-personal/vigentes', [FirmanteAccionPersonalController::class, 'vigentes']);
        Route::get('movimientos/{movimientoId}/accion-personal-pdf', [AccionPersonalPdfController::class, 'generar']);
        // La vista previa con la marca BORRADOR (fase 6.2): de quien prepara.
        Route::get('movimientos/{movimientoId}/vista-previa', [AccionPersonalPdfController::class, 'vistaPrevia'])
            ->middleware('permission:preparar-accion-personal');
        // Las solicitudes del portal (fase 6.3b): las atiende quien prepara acciones.
        Route::middleware('permission:preparar-accion-personal')->group(function () {
            Route::get('solicitudes-accion', [SolicitudAccionPersonalController::class, 'index']);
            Route::post('solicitudes-accion/{solicitud}/preparar', [SolicitudAccionPersonalController::class, 'preparar']);
            Route::post('solicitudes-accion/{solicitud}/rechazar', [SolicitudAccionPersonalController::class, 'rechazar']);
            Route::get('solicitudes-accion/{solicitud}/documento', [SolicitudAccionPersonalController::class, 'documento']);
        });
        Route::get('movimientos/{movimiento}', [MovimientoPersonalController::class, 'show']);
        // Editar un borrador es prepararlo: lo hace también el asistente, que
        // hasta la fase 1.3 lo creaba y no podía corregirlo.
        Route::put('movimientos/{movimiento}', [MovimientoPersonalController::class, 'update'])
            ->middleware('permission:preparar-accion-personal');
        // Anotar en lugar de corregir lo ya emitido (fase 1.4).
        Route::post('movimientos/{movimiento}/anotaciones', [MovimientoPersonalController::class, 'anotar'])
            ->middleware('permission:preparar-accion-personal');
        // La posesión, o el «se presentó a laborar» del ocasional (fase 6.4):
        // la registra quien notifica, como el resto de lo que pasa después.
        Route::post('movimientos/{movimiento}/posesion', [MovimientoPersonalController::class, 'registrarPosesion'])
            ->middleware('permission:notificar-accion-personal');
        // El reintegro nace de la ausencia que cierra (fase 2.4).
        Route::post('movimientos/{movimiento}/reintegro', [MovimientoPersonalController::class, 'reintegrar'])
            ->middleware('permission:preparar-accion-personal');
        // El permiso del paso concreto lo mira TransicionarMovimientoRequest.
        Route::put('movimientos/{movimiento}/transicionar', [MovimientoPersonalController::class, 'transicionar'])
            ->middleware('permission:suscribir-accion-personal|registrar-accion-personal|notificar-accion-personal|anular-accion-personal');

        // El certificado laboral lo emite Talento Humano, nunca el propio
        // servidor: así lo pidió la UATH el 2026-09-25 —quieren decidir ellos
        // a quién se le entrega y, al emitirlo, enviarlo por correo—. Hasta el
        // 2026-09-25 la ruta no pedía rol ni el controlador autorizaba, y
        // cualquier usuario autenticado podía pedir el de otra persona: nombre,
        // cédula y el historial completo de vínculos.
        // 'descargar' se retiró: el PDF ya no se guarda en disco, sale en la
        // misma respuesta. Servía una carpeta que nadie limpiaba y que
        // acumulaba copias con cédula y remuneración.
        Route::get('servidores/{id}/certificado-laboral', [CertificadoLaboralController::class, 'generar'])
            ->middleware('role:admin-uath|asistente-uath');

        // Los títulos y cursos que los servidores registraron desde el portal
        // y esperan a Talento Humano. Mismo rol que el historial académico.
        Route::get('formacion-por-validar', [HistorialAcademicoController::class, 'porValidar'])
            ->middleware('role:admin-uath|asistente-uath');

        // Las cargas familiares que los servidores piden desde «Mi perfil»
        // (docs/diseno-cargas-familiares-portal.md, 6.2). Los mismos roles
        // que gestionan las cargas en el expediente; nadie resuelve las de su
        // propia familia (lo impide el servicio).
        Route::prefix('cargas-por-validar')
            ->middleware('role:admin-uath|asistente-uath')
            ->name('cargas-por-validar.')
            ->group(function () {
                Route::get('/', [SolicitudCargaFamiliarController::class, 'index'])->name('index');
                Route::post('{solicitud}/validar', [SolicitudCargaFamiliarController::class, 'validar'])
                    ->whereNumber('solicitud')->name('validar');
                Route::post('{solicitud}/rechazar', [SolicitudCargaFamiliarController::class, 'rechazar'])
                    ->whereNumber('solicitud')->name('rechazar');
                Route::get('{solicitud}/documento', [SolicitudCargaFamiliarController::class, 'documento'])
                    ->whereNumber('solicitud')->name('documento');
            });

        // La bitácora de lo ya emitido. Mismo rol que emitir: quien no puede
        // entregar un certificado tampoco tiene por qué saber a quién se le
        // entregó uno ni cuándo.
        Route::get('servidores/{id}/certificados-emitidos', [CertificadoLaboralController::class, 'emitidos'])
            ->middleware('role:admin-uath|asistente-uath')
            ->name('certificados.emitidos');

        // Historiales gestionados por la UATH
        Route::prefix('servidores/{servidorId}')
            ->middleware('role:admin-uath|asistente-uath')
            ->group(function () {
                // 'store' queda fuera: un vínculo no se crea suelto, se crea
                // desde la acción de personal que lo respalda (regla de
                // Talento Humano). El alta directa sobrevive solo para carga
                // histórica, detrás de un rol aparte —ver más abajo—, porque
                // se salta la máquina de estados y no deja acción de personal.
                // Indicador de salud ocupacional: cuántos permisos por
                // enfermedad pidió en el último año. Sin motivos ni fechas —la
                // observación del permiso es texto libre y puede llevar un
                // diagnóstico—, solo la cifra.
                Route::get('ausentismo-salud', AusentismoSaludController::class)
                    ->name('expediente.ausentismoSalud');

                Route::get('actividad-laboral', [ContratoServidorController::class, 'actividadLaboral'])
                    ->name('contratos.actividadLaboral');

                // Aquí estaban `GET contratos` y `GET contratos/{id}`, retirados
                // el 2026-10-04: ninguna pantalla los pedía —la pestaña Laboral
                // lee `actividad-laboral`— y `show` respondía fuera de ApiResponse.

                Route::post('contratos', [ContratoServidorController::class, 'store'])
                    ->middleware('role:admin-ti')
                    ->name('contratos.store');
                // Aquí estaba `PUT contratos/{contrato}/cerrar`, retirado el
                // 2026-10-03: cerraba el vínculo sin acción de personal y sin
                // devolver el puesto. Un vínculo se cierra con la cesación.
                // Único campo editable de un vínculo ya creado: el plazo
                // (prórroga o corrección de digitación), siempre con motivo.
                Route::put('contratos/{contrato}/plazo', [ContratoServidorController::class, 'reprogramarPlazo'])
                    ->name('contratos.plazo');
                Route::apiResource('discapacidades', DiscapacidadServidorController::class)->except(['show']);
                Route::apiResource('enfermedades', EnfermedadCatastroficaServidorController::class)->except(['show']);

                // Historial académico
                Route::get('historial-academico', [HistorialAcademicoController::class, 'index']);
                Route::post('historial-academico', [HistorialAcademicoController::class, 'store']);
                Route::put('historial-academico/{id}', [HistorialAcademicoController::class, 'update']);
                Route::delete('historial-academico/{id}', [HistorialAcademicoController::class, 'destroy']);
                // Lo que el servidor registra desde «Mi perfil» llega por
                // validar, con su PDF (2026-10-17).
                Route::post('historial-academico/{id}/validar', [HistorialAcademicoController::class, 'validar'])
                    ->whereNumber('id');
                Route::post('historial-academico/{id}/rechazar', [HistorialAcademicoController::class, 'rechazar'])
                    ->whereNumber('id');
                Route::get('historial-academico/{id}/documento', [HistorialAcademicoController::class, 'documento'])
                    ->whereNumber('id');

                // Cargas familiares
                Route::get('cargas-familiares', [CargaFamiliarController::class, 'index']);
                Route::post('cargas-familiares', [CargaFamiliarController::class, 'store']);
                Route::put('cargas-familiares/{id}', [CargaFamiliarController::class, 'update']);
                Route::delete('cargas-familiares/{id}', [CargaFamiliarController::class, 'destroy']);
                Route::post('cargas-familiares/{id}/toggle-estado', [CargaFamiliarController::class, 'toggleEstado']);

                // Declaraciones juramentadas
                Route::get('declaraciones-juramentadas', [DeclaracionJuramentadaController::class, 'index']);
                Route::post('declaraciones-juramentadas', [DeclaracionJuramentadaController::class, 'store']);
                Route::delete('declaraciones-juramentadas/{id}', [DeclaracionJuramentadaController::class, 'destroy']);
                Route::get('declaraciones-juramentadas/exportar', [DeclaracionJuramentadaController::class, 'exportar']);
                Route::get('declaraciones-juramentadas/{id}/documento', [DeclaracionJuramentadaController::class, 'verDocumento']);
                Route::put('declaraciones-juramentadas/{id}', [DeclaracionJuramentadaController::class, 'update'])->name('declaraciones.update');

            });

        // Cuentas bancarias. Deciden a dónde va el sueldo: hasta el 2026-09-19
        // no pedían rol y cualquier usuario autenticado podía listar, crear o
        // marcar como principal una cuenta en el expediente de otro.
        Route::prefix('servidores/{id}/cuentas-bancarias')
            ->middleware('role:admin-uath|asistente-uath')
            ->group(function () {
                Route::get('/', [CuentaBancariaServidorController::class, 'index']);
                Route::post('/', [CuentaBancariaServidorController::class, 'store']);
                Route::put('{cuenta}', [CuentaBancariaServidorController::class, 'update']);
                Route::delete('{cuenta}', [CuentaBancariaServidorController::class, 'destroy']);
                Route::post('{cuenta}/set-principal', [CuentaBancariaServidorController::class, 'setPrincipal']);
            });

        // Subrogaciones y encargos. Hasta el 2026-09-28 ninguna de las seis
        // rutas pedía rol y el policy autorizaba la lectura a cualquier usuario
        // autenticado: un servidor raso listaba las subrogaciones de toda la
        // institución y el historial de cualquier persona. El rol va aquí
        // además del policy. admin-ti salió de las dos listas el 2026-10-26,
        // al dejar de ser superusuario (docs/plan-roles-y-accesos.md, PR 3).
        Route::prefix('subrogaciones')->group(function () {
            Route::middleware('role:admin-uath|asistente-uath|auditor')
                ->group(function () {
                    // 'activas' se retiró el 2026-09-28: no lo consumía nadie.
                    // A «quién ejerce hoy» responde el organigrama, con la
                    // relación UnidadAdministrativa::subrogacionesVigentes().
                    Route::get('vigentes', [SubrogacionController::class, 'listarVigentes']);
                });

            // Sin rol a propósito: el historial de un servidor también lo abre
            // el propio interesado, y eso depende de qué servidor se pide.
            // Lo decide SubrogacionPolicy::verDeServidor().
            Route::get('servidor/{servidorId}', [SubrogacionController::class, 'listarPorServidor']);

            Route::middleware('role:admin-uath|asistente-uath')->group(function () {
                // De qué ausencia del titular sale la subrogación (fase 5.2).
                Route::get('ausencias-del-titular', [SubrogacionController::class, 'ausenciasDelTitular']);
                Route::post('/', [SubrogacionController::class, 'registrar']);
                Route::put('{id}/finalizar', [SubrogacionController::class, 'finalizar']);
                Route::put('{id}/cancelar', [SubrogacionController::class, 'cancelar']);
            });
        });
    });

    // Módulo 03: de la nómina solo quedan las novedades. Desde el 2026-10-20
    // el SGTH es informativo: no calcula roles ni rubros, eso lo hace
    // Financiero en su sistema, y el SGTH le dice lo que pasó en el mes.
    Route::prefix('nomina')->group(function () {
        // Las novedades del mes (fase 7.3a): verlas y descargarlas, y
        // entregarlas, que congela el mes.
        Route::get('novedades', [NovedadNominaController::class, 'index'])
            ->middleware('permission:ver-novedades-nomina');
        Route::get('novedades/excel', [NovedadNominaController::class, 'excel'])
            ->middleware('permission:ver-novedades-nomina');
        Route::post('novedades/entregar', [NovedadNominaController::class, 'entregar'])
            ->middleware('permission:entregar-novedades-nomina');
    });

    // Módulo 04: Asistencia, Permisos y Vacaciones
    Route::prefix('asistencia')->group(function () {
        // Biométrico (Sirha7). Se consulta en vivo por procedimientos; lo
        // único que escribe es la marcación en línea.
        Route::get('marcaciones', [MarcacionController::class, 'index']);

        // Diez por minuto y por usuario: una persona marca cuatro veces al
        // día, y el doble toque ya lo absorbe el procedimiento.
        Route::post(
            'marcaciones/online',
            [MarcacionController::class,
                'registrarOnline']
        )->middleware('throttle:10,1')
            ->name('asistencia.marcaciones.online');

        Route::get(
            'marcaciones/estado-hoy',
            [MarcacionController::class,
                'estadoHoy']
        )->name('asistencia.marcaciones.estado-hoy');

        // Vacaciones
        Route::prefix('vacaciones')->group(function () {
            Route::get('/', [VacacionController::class, 'index']);
            Route::post('/', [VacacionController::class, 'store']);
            Route::get('saldo/{servidorId}', [VacacionController::class, 'saldo']);
            // Lo que descontarían unas fechas, con el descanso que sigue (2026-10-20).
            Route::get('conteo', [VacacionController::class, 'conteo']);
            Route::get('{id}/exportar', [VacacionController::class, 'exportar'])
                ->name('asistencia.vacaciones.exportar');
            Route::put('{id}', [VacacionController::class, 'update']);
            // Pendiente o aprobada sin comenzar; la aprobada devuelve sus días.
            Route::post('{id}/anular', [VacacionController::class, 'anular'])
                ->whereNumber('id')
                ->name('asistencia.vacaciones.anular');
        });

        // Certificados médicos del dispensario: Talento Humano y Trabajo Social
        // los aprueban y los registran en Sirha7 (decisión del 2026-10-08). Lo
        // decide la policy, como el resto del módulo.
        Route::prefix('certificados-medicos')->group(function () {
            Route::get('/', [CertificadoAprobacionController::class, 'index'])
                ->name('asistencia.certificados.index');
            Route::get('{id}/sirha7', [CertificadoAprobacionController::class, 'previa'])
                ->whereNumber('id')
                ->name('asistencia.certificados.sirha7.previa');
            Route::post('{id}/aprobar-sirha7', [CertificadoAprobacionController::class, 'aprobar'])
                ->whereNumber('id')
                ->name('asistencia.certificados.sirha7.aprobar');
            Route::post('{id}/aprobar-sin-sirha7', [CertificadoAprobacionController::class, 'aprobarSinSirha7'])
                ->whereNumber('id')
                ->name('asistencia.certificados.aprobar-sin-sirha7');
        });

        // Las licencias con remuneración, fuera de las vacaciones (fase 5.1).
        // Quién puede cada cosa lo dice LicenciaPolicy.
        Route::prefix('licencias')->group(function () {
            Route::get('/', [LicenciaController::class, 'index'])->name('asistencia.licencias.index');
            Route::get('causas', [LicenciaController::class, 'causas'])->name('asistencia.licencias.causas');
            Route::post('/', [LicenciaController::class, 'store'])->name('asistencia.licencias.store');
            Route::get('{id}/documento', [LicenciaController::class, 'documento'])
                ->whereNumber('id')
                ->name('asistencia.licencias.documento');
            Route::get('{id}/sirha7', [LicenciaController::class, 'previa'])
                ->whereNumber('id')
                ->name('asistencia.licencias.sirha7.previa');
            Route::post('{id}/aprobar-sirha7', [LicenciaController::class, 'aprobar'])
                ->whereNumber('id')
                ->name('asistencia.licencias.sirha7.aprobar');
            Route::post('{id}/aprobar-sin-sirha7', [LicenciaController::class, 'aprobarSinSirha7'])
                ->whereNumber('id')
                ->name('asistencia.licencias.aprobar-sin-sirha7');
            Route::post('{id}/anular', [LicenciaController::class, 'anular'])
                ->whereNumber('id')
                ->name('asistencia.licencias.anular');
        });

        // Permisos
        Route::prefix('permisos')->group(function () {
            Route::get('/', [PermisoServidorController::class, 'index']);
            Route::post('/', [PermisoServidorController::class, 'store']);
            // Antes que '{id}', y con '{id}' restringido a números: si no, el
            // comodín se traga 'folio/PER-2026-00045' y lo intenta buscar como
            // id. Es el mismo tropiezo que ya costó un arreglo en Nómina y
            // Actividades (933de6c).
            Route::get('folio/{folio}', [PermisoServidorController::class, 'showPorFolio'])
                ->name('asistencia.permisos.por-folio');

            Route::get('{id}', [PermisoServidorController::class, 'show'])
                ->whereNumber('id');
            Route::get('{id}/exportar', [PermisoServidorController::class, 'exportar'])
                ->whereNumber('id')
                ->name('asistencia.permisos.exportar');
            // Sin rol de ruta: el titular también anula el suyo, y lo decide la
            // policy. El middleware dejaba pasar a asistente-uath para que la
            // policy lo rechazara, y dejaba fuera al titular.
            Route::put('{id}/anular', [PermisoServidorController::class, 'anular'])
                ->whereNumber('id');

            // Confirmar, validar y rechazar tampoco llevan rol de ruta: lo
            // decide la policy con permisos, que es lo mismo que mira el
            // frontend para mostrar u ocultar cada botón. Con el rol aquí y el
            // permiso en la policy, el asistente pasaba la ruta de rechazar y
            // recibía un 403.
            Route::post('confirmar/{folio}', [PermisoServidorController::class, 'confirmar']);
            Route::post('{id}/validar-ts', [PermisoServidorController::class, 'validar'])
                ->whereNumber('id');

            // Aprobar en Sirha7, el biométrico (decisión de TH, 2026-10-08):
            // los tipos para el selector, lo que se va a registrar y el
            // registro. Lo decide la policy, como el resto del módulo.
            Route::get('sirha7/tipos', [PermisoSirha7Controller::class, 'tipos'])
                ->name('asistencia.permisos.sirha7.tipos');
            Route::get('{id}/sirha7', [PermisoSirha7Controller::class, 'previa'])
                ->whereNumber('id')
                ->name('asistencia.permisos.sirha7.previa');
            Route::post('{id}/aprobar-sirha7', [PermisoSirha7Controller::class, 'aprobar'])
                ->whereNumber('id')
                ->name('asistencia.permisos.sirha7.aprobar');

            // Recepción rechaza el documento físico que llega mal. El estado
            // RECHAZADO estaba en el enum desde el principio y nada lo asignaba.
            Route::post('{id}/rechazar', [PermisoServidorController::class, 'rechazar'])
                ->whereNumber('id');

            // Deshace una confirmación hecha por error y devuelve el saldo
            // vacacional descontado. Sin rol de ruta: lo decide la policy,
            // porque el permiso que exige (anular-permiso) hoy solo lo tiene
            // admin-uath y ponerlo también aquí lo diría dos veces.
            Route::post('{id}/revertir-confirmacion',
                [PermisoServidorController::class, 'revertirConfirmacion']);
        });
    });

    Route::prefix('asistencia/periodos-vacaciones')
        ->group(function () {
            Route::get(
                'servidores/{servidorId}/resumen',
                [PeriodoVacacionController::class,
                    'resumen']
            )->name('periodos.resumen');

            Route::post(
                'servidores/{servidorId}/generar',
                [PeriodoVacacionController::class,
                    'generar']
            )->name('periodos.generar');

            // Qué cambiaría al forzar el recálculo. Solo lee: alimenta el
            // diálogo de confirmación con el saldo de antes y el de después.
            Route::get(
                'servidores/{servidorId}/recalcular-cerrado/previsualizacion',
                [PeriodoVacacionController::class,
                    'previsualizarRecalculo']
            )->name('periodos.recalcular-cerrado.previsualizacion');

            // Recálculo deliberado de un año ya cerrado. Ruta propia y no una
            // bandera de 'generar': alterar un saldo certificado no puede ser
            // el efecto colateral de una operación de rutina.
            Route::post(
                'servidores/{servidorId}/recalcular-cerrado',
                [PeriodoVacacionController::class,
                    'recalcularCerrado']
            )->name('periodos.recalcular-cerrado');

            Route::post(
                'generar-todos',
                [PeriodoVacacionController::class,
                    'generarTodos']
            )->name('periodos.generar-todos');

            // Tope de acumulación: quién está cerca o por encima, y vencer el
            // excedente de un servidor. Nada vence solo.
            Route::get(
                'excedentes',
                [PeriodoVacacionController::class, 'excedentes']
            )->name('periodos.excedentes');

            Route::post(
                'servidores/{servidorId}/vencer-excedente',
                [PeriodoVacacionController::class, 'vencerExcedente']
            )->name('periodos.vencer-excedente');
        });

    // El consolidado es un informe de toda la institución: nombres, cédulas,
    // unidades y horas de ausencia de cada servidor. Estaba abierto a
    // cualquier usuario autenticado —un servidor raso podía descargarlo en
    // Excel—. Se cierra al mismo permiso que gobierna «ver los permisos de
    // todos», que es exactamente lo que este informe hace, en agregado.
    Route::prefix('asistencia/consolidado-permisos')
        ->middleware('permission:ver-permisos-todos')
        ->group(function () {
            Route::get(
                '/',
                [ConsolidadoPermisoController::class,
                    'consolidado']
            )->name('asistencia.consolidado');

            Route::get(
                'exportar-excel',
                [ConsolidadoPermisoController::class,
                    'exportarExcel']
            )->name('asistencia.consolidado.excel');

            Route::get(
                'exportar-pdf',
                [ConsolidadoPermisoController::class,
                    'exportarPdf']
            )->name('asistencia.consolidado.pdf');
        });

    // Módulo 05: Sistema de Gestión Documental (SGD)
    Route::prefix('sgd')->group(function () {
        Route::post('documentos', [DocumentoInstitucionalController::class, 'store']);
        Route::get('documentos/{id}/generar-enlace', [DocumentoInstitucionalController::class, 'generarEnlace']);
    });

    // Módulo 06: Autoservicio
    Route::prefix('autoservicio')->group(function () {
        Route::get('mis-permisos', [AutoservicioController::class, 'misPermisos']);
        // Los reposos del dispensario, que desde el 2026-10-08 no son permisos.
        Route::get('mis-certificados-medicos', MisCertificadosMedicosController::class);
        // De dónde elige el servidor a su jefe inmediato al registrar un permiso
        // desde el portal: el listado de expedientes está cerrado a Talento Humano.
        Route::get('companeros-de-unidad', [AutoservicioController::class, 'companerosDeUnidad']);
        Route::get('mis-vacaciones', [AutoservicioController::class, 'misVacaciones']);
        Route::get('mi-expediente', [AutoservicioController::class, 'miExpediente']);
        // Las actividades laborales que anota el servidor: las suyas, nadie
        // más las ve. Se corrigen solo las del mes en curso.
        Route::middleware('permission:registrar-actividades')->group(function () {
            Route::get('mis-actividades', [MisActividadesController::class, 'index']);
            Route::post('mis-actividades', [MisActividadesController::class, 'store']);
            Route::put('mis-actividades/{actividad}', [MisActividadesController::class, 'update'])->whereNumber('actividad');
            Route::delete('mis-actividades/{actividad}', [MisActividadesController::class, 'destroy'])->whereNumber('actividad');
        });
        Route::get('mis-actividades/pdf', [MisActividadesController::class, 'pdf'])
            ->middleware('permission:exportar-informe-actividades');
        // «Mis documentos laborales» (fase 6.3a): lo notificado y registrado,
        // su PDF y el acuse de recibo. Siempre los del propio servidor.
        // «Mis equipos» (TIC, fase 1.3): los que tiene a su cargo o usa.
        Route::get('mis-equipos', [MisEquiposController::class, 'index'])
            ->middleware('permission:acceso-autoservicio');
        // «Mi credencial» (Comunicación Social, fase 3.1): la digital y su PDF.
        Route::get('mi-credencial', [MiCredencialController::class, 'show'])
            ->middleware('permission:acceso-autoservicio');
        Route::get('mi-credencial/pdf', [MiCredencialController::class, 'pdf'])
            ->middleware('permission:acceso-autoservicio');
        // «Noticias y comunicados» (docs/diseno-noticias-comunicados.md, 5): lo
        // que Talento Humano publicó para quien pregunta. Abrir una la deja
        // leída; lo que no le corresponde responde 404.
        Route::prefix('comunicados')->middleware('permission:acceso-autoservicio')->group(function () {
            Route::get('/', [MisComunicadosController::class, 'index']);
            Route::get('resumen', [MisComunicadosController::class, 'resumen']);
            Route::get('{comunicado}', [MisComunicadosController::class, 'show'])->whereNumber('comunicado');
            Route::post('{comunicado}/confirmacion', [MisComunicadosController::class, 'confirmar'])->whereNumber('comunicado');
            Route::get('{comunicado}/adjuntos/{adjunto}', [MisComunicadosController::class, 'adjunto'])
                ->whereNumber(['comunicado', 'adjunto']);
        });
        // «Mi perfil» (2026-10-17): la formación propia y la hoja de vida en PDF.
        // Los títulos y cursos que registra el propio servidor quedan por
        // validar hasta que los revisa Talento Humano.
        Route::middleware('permission:acceso-autoservicio')->group(function () {
            Route::get('mi-formacion-academica', [MiFormacionController::class, 'index']);
            Route::post('mi-formacion-academica', [MiFormacionController::class, 'store']);
            Route::delete('mi-formacion-academica/{id}', [MiFormacionController::class, 'destroy'])->whereNumber('id');
            Route::get('mi-formacion-academica/{id}/documento', [MiFormacionController::class, 'documento'])->whereNumber('id');
        });
        Route::get('mi-hoja-de-vida', [MiPerfilController::class, 'hojaDeVida'])
            ->middleware('permission:acceso-autoservicio');
        Route::get('mis-equipos/{asignacion}/acta', [MisEquiposController::class, 'acta'])
            ->middleware('permission:acceso-autoservicio');
        // Préstamo de equipos de TIC (fase 2.1): la disponibilidad sin decir
        // quién tiene cada equipo, pedir, los propios y cancelarlos.
        Route::prefix('mis-prestamos')->middleware('permission:acceso-autoservicio')->group(function () {
            Route::get('/', [MisPrestamosController::class, 'index']);
            Route::post('/', [MisPrestamosController::class, 'store']);
            Route::get('disponibilidad', [MisPrestamosController::class, 'disponibilidad']);
            // Con nombre: las dos rutas de las reglas comparten método, y sin él
            // el generador de tipos les daba el mismo identificador a ambas.
            Route::get('reglas', [ReglasPrestamoController::class, 'show'])->name('autoservicio.prestamos.reglas');
            Route::get('{prestamo}', [MisPrestamosController::class, 'show'])->whereNumber('prestamo');
            Route::post('{prestamo}/cancelar', [MisPrestamosController::class, 'cancelar'])->whereNumber('prestamo');
        });
        Route::get('mis-documentos', [MisDocumentosLaboralesController::class, 'index']);
        Route::get('mis-documentos/{movimiento}/pdf', [MisDocumentosLaboralesController::class, 'pdf']);
        Route::post('mis-documentos/{movimiento}/acuse', [MisDocumentosLaboralesController::class, 'acuse']);
        // Las solicitudes de renuncia, licencia sin remuneración y jubilación
        // (fase 6.3b). Siempre las del propio servidor.
        Route::get('mis-solicitudes', [MisSolicitudesAccionController::class, 'index']);
        Route::post('mis-solicitudes', [MisSolicitudesAccionController::class, 'store']);
        Route::post('mis-solicitudes/{solicitud}/retirar', [MisSolicitudesAccionController::class, 'retirar']);
        Route::get('mis-solicitudes/{solicitud}/documento', [MisSolicitudesAccionController::class, 'documento']);

        // Aquí estaba también `POST solicitar-cita`, retirada el 2026-09-11.
        // Nunca funcionó: escribía en `agendas_medicas` columnas que no existen
        // y respondía 500 siempre; ninguna pantalla la usaba. Las citas las
        // registra el dispensario al admitir al paciente, y la ausencia la
        // justifica el certificado médico, que ya crea su permiso con folio.
        Route::get('mi-historia-clinica', [AutoservicioController::class, 'miHistoriaClinica']);

        // «Mis exámenes» (fase 6 de docs/diseno-examenes-medicos.md) se retiró
        // el 2026-10-25: los resultados los suben solo el médico y el
        // odontólogo, y el servidor no ve sus órdenes en el Portal.

        // `mis-cargas-familiares` vuelve el 2026-10-19 con otro contrato. El
        // CRUD que hubo aquí se retiró el 2026-10-03: ninguna pantalla lo
        // usaba y no pedía rol, así que cualquier servidor podía darse de alta
        // familiares —que entran como pacientes del Dispensario— o borrar los
        // que registró Talento Humano. Ahora el servidor solo presenta
        // solicitudes, y nada cuenta hasta que Talento Humano las valida
        // (docs/diseno-cargas-familiares-portal.md).
        Route::prefix('mis-cargas-familiares')->middleware('permission:acceso-autoservicio')->group(function () {
            Route::get('/', [MisCargasFamiliaresController::class, 'index']);
            Route::post('solicitudes', [MisCargasFamiliaresController::class, 'store']);
            Route::post('solicitudes/{solicitud}/retirar', [MisCargasFamiliaresController::class, 'retirar'])
                ->whereNumber('solicitud');
            Route::get('solicitudes/{solicitud}/documento', [MisCargasFamiliaresController::class, 'documento'])
                ->whereNumber('solicitud');
        });
    });

    // Módulo 07: Selección e Incorporación
    //
    // Por permiso, no por rol (decisión 6 de TH, 2026-10-05). Antes bastaba
    // con ser admin-uath o analista-uath para todo: el analista creaba,
    // publicaba, borraba y declaraba ganadores. El rol de analista se retiró
    // el 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7); crear, publicar e
    // incorporar siguen siendo de quien gestiona convocatorias (admin-uath).
    Route::prefix('seleccion')->group(function () {
        // Leer: listados, detalle, criterios, plantillas y calificaciones.
        Route::middleware('permission:ver-postulantes')->group(function () {
            // Reclutamiento express: contenedores permanentes por modalidad.
            Route::get('express/resumen', [ContenedorExpressController::class, 'resumen']);
            Route::get('express/anios', [ContenedorExpressController::class, 'aniosDisponibles']);
            Route::get('express/{convocatoriaId}/aspirantes', [ContenedorExpressController::class, 'aspirantes']);

            Route::get('convocatorias', [ConvocatoriaController::class, 'index']);
            Route::get('convocatorias/{id}', [ConvocatoriaController::class, 'show']);
            Route::get('convocatorias/{convocatoriaId}/postulantes', [PostulanteController::class, 'index']);
            // Los documentos están en el disco privado (2026-10-04): se bajan por aquí.
            Route::get('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/documentos/{documentoId}', [PostulanteController::class, 'descargarDocumento']);
            Route::get('convocatorias/{convocatoriaId}/criterios', [CriterioEvaluacionController::class, 'index']);
            Route::get('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/calificaciones', [CalificacionController::class, 'obtener']);
            Route::get('plantillas', [PlantillaEvaluacionController::class, 'index']);
            Route::get('plantillas/{id}', [PlantillaEvaluacionController::class, 'show']);
        });

        // Calificar: lo único que hace el analista.
        // Sin `postulantes/{id}/calificar` (2026-10-05): ponía méritos y
        // oposición a mano, sin criterios. Se califica por criterios.
        Route::post('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/calificaciones', [CalificacionController::class, 'guardar'])
            ->middleware('permission:evaluar-postulantes');

        // Gestionar: la convocatoria, sus candidatos, sus criterios y sus ganadores.
        Route::middleware('permission:gestionar-convocatorias')->group(function () {
            Route::post('convocatorias', [ConvocatoriaController::class, 'store']);
            Route::patch('convocatorias/{id}', [ConvocatoriaController::class, 'update']);
            Route::delete('convocatorias/{id}', [ConvocatoriaController::class, 'destroy']);
            Route::patch('convocatorias/{id}/publicar', [ConvocatoriaController::class, 'publicar']);
            // Desierta o cancelada, con motivo: el PATCH ya no cambia el estado.
            Route::post('convocatorias/{id}/cerrar', [ConvocatoriaController::class, 'cerrar']);

            Route::post('convocatorias/{convocatoriaId}/postulantes', [PostulanteController::class, 'store']);
            Route::patch('convocatorias/{convocatoriaId}/postulantes/{postulanteId}', [PostulanteController::class, 'update']);
            Route::delete('convocatorias/{convocatoriaId}/postulantes/{postulanteId}', [PostulanteController::class, 'destroy']);
            Route::post('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/documentos', [PostulanteController::class, 'subirDocumento']);
            Route::delete('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/documentos/{documentoId}', [PostulanteController::class, 'eliminarDocumento']);

            Route::post('convocatorias/{convocatoriaId}/criterios', [CriterioEvaluacionController::class, 'store']);
            Route::delete('convocatorias/{convocatoriaId}/criterios/{criterioId}', [CriterioEvaluacionController::class, 'destroy']);

            Route::post('plantillas', [PlantillaEvaluacionController::class, 'store']);
            Route::delete('plantillas/{id}', [PlantillaEvaluacionController::class, 'destroy']);
            Route::post('plantillas/{plantillaId}/criterios', [PlantillaEvaluacionController::class, 'agregarCriterio']);
            Route::delete('plantillas/{plantillaId}/criterios/{criterioId}', [PlantillaEvaluacionController::class, 'eliminarCriterio']);
            Route::post('plantillas/{plantillaId}/aplicar/{convocatoriaId}', [PlantillaEvaluacionController::class, 'aplicarAConvocatoria']);

            Route::post('convocatorias/{id}/declarar-ganador', [SeleccionController::class, 'declararGanador']);
            // La vacante que deja un no apto la cubre el siguiente del ranking.
            Route::post('convocatorias/{id}/declarar-siguiente', [SeleccionController::class, 'declararSiguiente']);
        });

        // La inducción del incorporado (2026-10-05): se creaba al incorporar y
        // ninguna pantalla la mostraba.
        Route::patch('onboardings/{id}', [OnboardingController::class, 'update'])
            ->middleware('permission:gestionar-onboarding');
    });

    // Tablero de Inicio de Tecnologías (fase 4.1): entra quien atiende la mesa
    // de ayuda, consulta el inventario o gestiona préstamos; cada bloque sale
    // con su permiso.
    Route::get('tic/tablero', TableroTicController::class)
        ->middleware('permission:gestionar-tickets|ver-tickets-todos|ver-inventario-ti|gestionar-inventario-ti|gestionar-prestamos');

    // Reportes de Tecnologías (fase 4.2), en pantalla, Excel o PDF con
    // `formato`. Los del inventario, a quien lo consulta; los de la mesa de
    // ayuda —el trabajo de cada técnico—, a quien la ve entera.
    Route::prefix('tic/reportes')->group(function () {
        $inventario = 'permission:ver-inventario-ti|gestionar-inventario-ti';
        Route::get('inventario', [ReporteTicController::class, 'inventario'])->middleware($inventario);
        Route::get('obsolescencia', [ReporteTicController::class, 'obsolescencia'])->middleware($inventario);
        // Los de consumibles (fase 5.4d): el consumo y el saldo a una fecha.
        Route::get('consumos', [ReporteTicController::class, 'consumos'])->middleware($inventario);
        Route::get('saldo-consumibles', [ReporteTicController::class, 'saldoConsumibles'])->middleware($inventario);
        Route::get('tickets', [ReporteTicController::class, 'tickets'])->middleware('permission:ver-tickets-todos');
        Route::get('satisfaccion', [ReporteTicController::class, 'satisfaccion'])->middleware('permission:ver-tickets-todos');
    });

    // Módulo 12 — Inventario de Bienes Informáticos
    //
    // Por permiso y no por rol (docs/diseno-subsistema-tic.md, 4.4): consultar
    // es `ver-inventario-ti` (técnico, jefe de TIC, auditor); registrar,
    // editar y entregar es `gestionar-inventario-ti`; el mantenimiento y la
    // baja tienen su propio permiso. Los recursos van con `only` porque
    // `apiResource` publicaba métodos que los controladores no tienen.
    Route::prefix('inventario')
        ->group(function () {
            $ver = 'permission:ver-inventario-ti|gestionar-inventario-ti';
            $gestionar = 'permission:gestionar-inventario-ti';

            // Bienes
            Route::apiResource('bienes', BienInformaticoController::class)
                ->only(['index', 'show'])
                ->middleware($ver);
            Route::apiResource('bienes', BienInformaticoController::class)
                ->only(['store', 'update', 'destroy'])
                ->middleware($gestionar);
            Route::get('bienes/{bien}/historial',
                [BienInformaticoController::class, 'historial'])
                ->middleware($ver);

            // Catálogos (fase 1.1). Hasta aquí existían las tablas de tipos y
            // marcas, pero ninguna ruta las llenaba: registrar un equipo pedía
            // ids que nadie podía dar de alta. Los nombres de ruta dicen a
            // `StoreCatalogoInventarioRequest` qué catálogo valida.
            Route::get('catalogos', [CatalogoInventarioController::class, 'formularios'])
                ->middleware($ver);
            Route::middleware($ver)->group(function () {
                Route::get('tipos', [CatalogoInventarioController::class, 'tipos'])->name('tipos.index');
                Route::get('marcas', [CatalogoInventarioController::class, 'marcas'])->name('marcas.index');
                Route::get('ubicaciones', [CatalogoInventarioController::class, 'ubicaciones'])->name('ubicaciones.index');
                Route::get('contratos-vigencia', [ContratoVigenciaController::class, 'index']);
                Route::get('contratos-vigencia/{contrato}', [ContratoVigenciaController::class, 'show'])
                    ->whereNumber('contrato');
            });
            Route::middleware($gestionar)->group(function () {
                Route::post('tipos', [CatalogoInventarioController::class, 'guardarTipo'])->name('tipos.store');
                Route::put('tipos/{tipo}', [CatalogoInventarioController::class, 'actualizarTipo'])->name('tipos.update');
                Route::delete('tipos/{tipo}', [CatalogoInventarioController::class, 'eliminarTipo'])->name('tipos.destroy');
                Route::post('subtipos', [CatalogoInventarioController::class, 'guardarSubtipo'])->name('subtipos.store');
                Route::put('subtipos/{subtipo}', [CatalogoInventarioController::class, 'actualizarSubtipo'])->name('subtipos.update');
                Route::delete('subtipos/{subtipo}', [CatalogoInventarioController::class, 'eliminarSubtipo'])->name('subtipos.destroy');
                Route::post('marcas', [CatalogoInventarioController::class, 'guardarMarca'])->name('marcas.store');
                Route::put('marcas/{marca}', [CatalogoInventarioController::class, 'actualizarMarca'])->name('marcas.update');
                Route::delete('marcas/{marca}', [CatalogoInventarioController::class, 'eliminarMarca'])->name('marcas.destroy');
                Route::post('ubicaciones', [CatalogoInventarioController::class, 'guardarUbicacion'])->name('ubicaciones.store');
                Route::put('ubicaciones/{ubicacion}', [CatalogoInventarioController::class, 'actualizarUbicacion'])->name('ubicaciones.update');
                Route::delete('ubicaciones/{ubicacion}', [CatalogoInventarioController::class, 'eliminarUbicacion'])->name('ubicaciones.destroy');
                Route::post('contratos-vigencia', [ContratoVigenciaController::class, 'store']);
                Route::put('contratos-vigencia/{contrato}', [ContratoVigenciaController::class, 'update']);
                Route::delete('contratos-vigencia/{contrato}', [ContratoVigenciaController::class, 'destroy']);
            });

            // La ubicación habitual de cada unidad y la mudanza en lote: la
            // ubicación es del equipo, esto solo la propone o la cambia en bloque.
            Route::get('ubicaciones-por-unidad', [UbicacionesBienController::class, 'index'])->middleware($ver);
            Route::get('ubicaciones-por-unidad/sugerida', [UbicacionesBienController::class, 'sugerida'])->middleware($ver);
            Route::put('ubicaciones-por-unidad/{unidad}', [UbicacionesBienController::class, 'fijar'])->whereNumber('unidad')->middleware($gestionar);
            Route::post('ubicaciones-por-unidad/mudanza', [UbicacionesBienController::class, 'mudanza'])->middleware($gestionar);

            // Asignaciones
            Route::apiResource('asignaciones', AsignacionBienController::class)
                ->only(['index', 'show'])
                ->middleware($ver);
            Route::post('asignaciones',
                [AsignacionBienController::class, 'store'])
                ->middleware($gestionar);
            // El acta de entrega-recepción: el papel que se firma al entregar
            // el equipo, y la única salida documental de este registro.
            Route::get('asignaciones/{asignacion}/acta',
                [AsignacionBienController::class, 'acta'])
                ->middleware($ver);
            // La devolución, que la desvinculación espera (fase 7.4a).
            Route::post('asignaciones/{asignacion}/devolver',
                [AsignacionBienController::class, 'devolver'])
                ->middleware($gestionar);
            // Traspaso y memorando de Quipux (fase 1.3).
            Route::post('asignaciones/{asignacion}/traspasar',
                [AsignacionBienController::class, 'traspasar'])
                ->middleware($gestionar);
            Route::post('asignaciones/{asignacion}/memorando',
                [AsignacionBienController::class, 'adjuntarMemorando'])
                ->middleware($gestionar);
            Route::get('asignaciones/{asignacion}/memorando',
                [AsignacionBienController::class, 'memorando'])
                ->middleware($ver);

            // Mantenimientos (fase 1.5). «tecnicos» antes del recurso, o
            // `{mantenimiento}` se lo come.
            Route::get('mantenimientos/tecnicos',
                [MantenimientoBienController::class, 'tecnicos'])
                ->middleware('permission:registrar-mantenimiento');
            Route::apiResource('mantenimientos', MantenimientoBienController::class)
                ->only(['index', 'show'])
                ->middleware($ver);
            Route::post('mantenimientos',
                [MantenimientoBienController::class, 'store'])
                ->middleware('permission:registrar-mantenimiento');
            Route::post('mantenimientos/{mantenimiento}/anular',
                [MantenimientoBienController::class, 'anular'])
                ->middleware('permission:registrar-mantenimiento');

            // Constatación física por QR (fase 5.2). Reemplaza `auditoria/*`,
            // que era simulada. La hace solo TIC; el auditor la consulta.
            Route::get('constataciones', [ConstatacionController::class, 'index'])->middleware($ver);
            Route::post('constataciones', [ConstatacionController::class, 'store'])->middleware($gestionar);
            Route::get('constataciones/{constatacion}', [ConstatacionController::class, 'show'])->whereNumber('constatacion')->middleware($ver);
            Route::get('constataciones/{constatacion}/acta', [ConstatacionController::class, 'acta'])->whereNumber('constatacion')->middleware($ver);
            Route::post('constataciones/{constatacion}/escanear', [ConstatacionController::class, 'escanear'])->whereNumber('constatacion')->middleware($gestionar);
            Route::post('constataciones/{constatacion}/items/{item}/mover', [ConstatacionController::class, 'mover'])
                ->whereNumber(['constatacion', 'item'])->middleware($gestionar);
            Route::post('constataciones/{constatacion}/cerrar', [ConstatacionController::class, 'cerrar'])->whereNumber('constatacion')->middleware($gestionar);
            // Si participa Activos Fijos y Bodega (5.2b): solo mientras está en curso.
            Route::patch('constataciones/{constatacion}/activos-fijos', [ConstatacionController::class, 'activosFijos'])->whereNumber('constatacion')->middleware($gestionar);

            // Plan de mantenimiento preventivo por campaña (fase 5.1). Planificar
            // es de quien gestiona el inventario; marcar lo hecho u omitido, de
            // quien registra mantenimientos.
            $registrar = 'permission:registrar-mantenimiento';
            Route::get('campanias', [CampaniaMantenimientoController::class, 'index'])->middleware($ver);
            Route::post('campanias', [CampaniaMantenimientoController::class, 'store'])->middleware($gestionar);
            Route::get('campanias/{campania}', [CampaniaMantenimientoController::class, 'show'])->whereNumber('campania')->middleware($ver);
            Route::put('campanias/{campania}', [CampaniaMantenimientoController::class, 'update'])->whereNumber('campania')->middleware($gestionar);
            Route::post('campanias/{campania}/equipos', [CampaniaMantenimientoController::class, 'agregar'])->whereNumber('campania')->middleware($gestionar);
            Route::delete('campanias/{campania}/equipos/{equipo}', [CampaniaMantenimientoController::class, 'quitar'])
                ->whereNumber(['campania', 'equipo'])->middleware($gestionar);
            Route::post('campanias/{campania}/realizar', [CampaniaMantenimientoController::class, 'realizar'])->whereNumber('campania')->middleware($registrar);
            Route::post('campanias/{campania}/omitir', [CampaniaMantenimientoController::class, 'omitir'])->whereNumber('campania')->middleware($registrar);
            Route::post('campanias/{campania}/cerrar', [CampaniaMantenimientoController::class, 'cerrar'])->whereNumber('campania')->middleware($gestionar);

            // Consumibles de impresión y repuestos (fase 5.4a): registran los
            // técnicos y el jefe de TIC; el auditor consulta [P10]. Las rutas
            // fijas antes de `{consumible}`.
            Route::get('consumibles', [ConsumibleController::class, 'index'])->middleware($ver);
            Route::post('consumibles', [ConsumibleController::class, 'store'])->middleware($gestionar);
            Route::post('consumibles/ingresos', [MovimientoConsumibleController::class, 'ingresos'])->middleware($gestionar);
            Route::post('consumibles/entregas', [MovimientoConsumibleController::class, 'entregas'])->middleware($gestionar);
            Route::post('consumibles/movimientos/{movimiento}/anular', [MovimientoConsumibleController::class, 'anular'])
                ->whereNumber('movimiento')->middleware($gestionar);
            Route::get('consumibles/{consumible}', [ConsumibleController::class, 'show'])->whereNumber('consumible')->middleware($ver);
            Route::put('consumibles/{consumible}', [ConsumibleController::class, 'update'])->whereNumber('consumible')->middleware($gestionar);
            Route::get('consumibles/{consumible}/kardex', [MovimientoConsumibleController::class, 'kardex'])->whereNumber('consumible')->middleware($ver);
            Route::get('consumibles/{consumible}/kardex/excel', [MovimientoConsumibleController::class, 'kardexExcel'])->whereNumber('consumible')->middleware($ver);
            Route::post('consumibles/{consumible}/modelos', [ConsumibleController::class, 'agregarModelo'])->whereNumber('consumible')->middleware($gestionar);
            Route::delete('consumibles/{consumible}/modelos/{modelo}', [ConsumibleController::class, 'quitarModelo'])
                ->whereNumber(['consumible', 'modelo'])->middleware($gestionar);
            Route::post('consumibles/{consumible}/apertura', [MovimientoConsumibleController::class, 'apertura'])->whereNumber('consumible')->middleware($gestionar);
            Route::post('consumibles/{consumible}/ajustes', [MovimientoConsumibleController::class, 'ajuste'])->whereNumber('consumible')->middleware($gestionar);
            Route::get('bienes/{bien}/consumibles', [ConsumibleController::class, 'delEquipo'])->whereNumber('bien')->middleware($ver);

            // La solicitud de materiales a bodega (fase 5.4c): desde un ticket
            // «Consumibles» o sin él; lo despachado por Bodega es la entrega.
            Route::get('solicitudes-bodega', [SolicitudBodegaController::class, 'index'])->middleware($ver);
            Route::post('solicitudes-bodega', [SolicitudBodegaController::class, 'store'])->middleware($gestionar);
            Route::get('solicitudes-bodega/{solicitud}', [SolicitudBodegaController::class, 'show'])->whereNumber('solicitud')->middleware($ver);
            Route::get('solicitudes-bodega/{solicitud}/pdf', [SolicitudBodegaController::class, 'pdf'])->whereNumber('solicitud')->middleware($ver);
            Route::post('solicitudes-bodega/{solicitud}/despachar', [SolicitudBodegaController::class, 'despachar'])->whereNumber('solicitud')->middleware($gestionar);
            Route::post('solicitudes-bodega/{solicitud}/anular', [SolicitudBodegaController::class, 'anular'])->whereNumber('solicitud')->middleware($gestionar);

            // Bajas (fase 1.6): el técnico la propone con su informe técnico y
            // el jefe de TIC la aprueba con el memorando o la rechaza.
            Route::get('bajas',
                [BajaController::class, 'index'])
                ->middleware($ver);
            Route::get('bajas/{id}',
                [BajaController::class, 'show'])
                ->whereNumber('id')
                ->middleware($ver);
            Route::get('bajas/{id}/informe',
                [BajaController::class, 'informe'])
                ->whereNumber('id')
                ->middleware($ver);
            Route::get('bajas/{baja}/memorando',
                [BajaController::class, 'memorando'])
                ->middleware($ver);
            Route::post('bajas',
                [BajaController::class, 'store'])
                ->middleware($gestionar);
            Route::middleware('permission:dar-baja-bien')->group(function () {
                Route::post('bajas/{baja}/aprobar', [BajaController::class, 'aprobar']);
                Route::post('bajas/{baja}/rechazar', [BajaController::class, 'rechazar']);
                Route::post('bajas/{baja}/memorando', [BajaController::class, 'adjuntarMemorando']);
                Route::post('bajas/{baja}/entrega-activos', [BajaController::class, 'entregaActivos']);
            });

            // Etiquetas con QR (fase 1.6): la de un equipo o las del filtro
            // del listado. El lote no cuelga de «bienes/», o `{bien}` se lo come.
            Route::get('bienes/{bien}/etiqueta',
                [EtiquetaBienController::class, 'una'])
                ->middleware($ver);
            Route::get('etiquetas',
                [EtiquetaBienController::class, 'lote'])
                ->middleware($ver);

            // Préstamo de equipos (fase 2.1). Consultar, también el auditor;
            // el trámite y las reglas, `gestionar-prestamos`.
            Route::prefix('prestamos')->group(function () use ($ver) {
                $verPrestamos = $ver . '|gestionar-prestamos';
                $gestionarPrestamos = 'permission:gestionar-prestamos';

                Route::get('/', [PrestamoEquipoController::class, 'index'])->middleware($verPrestamos);
                Route::get('disponibilidad', [PrestamoEquipoController::class, 'disponibilidad'])->middleware($verPrestamos);
                Route::get('resumen', [PrestamoEquipoController::class, 'resumen'])->middleware($verPrestamos);
                // El uso de cada equipo (fase 2.4).
                Route::get('reporte-uso', [ReporteUsoPrestamoController::class, 'index'])->middleware($verPrestamos);
                Route::get('reporte-uso/excel', [ReporteUsoPrestamoController::class, 'excel'])->middleware($verPrestamos);
                Route::get('{id}/acta', [PrestamoEquipoController::class, 'acta'])->whereNumber('id')->middleware($verPrestamos);
                Route::get('reglas', [ReglasPrestamoController::class, 'show'])->middleware($verPrestamos)->name('inventario.prestamos.reglas');
                Route::put('reglas', [ReglasPrestamoController::class, 'update'])->middleware($gestionarPrestamos);
                Route::post('directo', [PrestamoEquipoController::class, 'directo'])->middleware($gestionarPrestamos);
                Route::get('{id}', [PrestamoEquipoController::class, 'show'])->whereNumber('id')->middleware($verPrestamos);
                Route::middleware($gestionarPrestamos)->group(function () {
                    foreach (['aprobar', 'rechazar', 'cancelar', 'entregar', 'recibir'] as $paso) {
                        Route::post("{prestamo}/{$paso}", [PrestamoEquipoController::class, $paso])->whereNumber('prestamo');
                    }
                });
            });
        });

    // Módulo 13 — Helpdesk
    //
    // Por permiso, y el alcance de cada ticket en `TicketPolicy`: el servidor
    // ve los suyos, el técnico su cola y los sin asignar, el jefe de TIC todos.
    // Hasta ahora el listado, el detalle y los comentarios no pedían nada.
    Route::prefix('helpdesk')
        ->group(function () {
            $verTickets = 'permission:ver-ticket-propio|gestionar-tickets|ver-tickets-todos';

            // Tickets (fase 3.1): el alcance en `TicketPolicy`; el trámite, una
            // acción por paso en `TicketEstadoService`. «catalogos» antes del
            // comodín.
            Route::get('tickets/catalogos', [TicketController::class, 'catalogos'])
                ->middleware($verTickets . '|crear-ticket');
            Route::get('tickets/resumen', [TicketController::class, 'resumen'])
                ->middleware('permission:gestionar-tickets|ver-tickets-todos');
            Route::get('tickets', [TicketController::class, 'index'])->middleware($verTickets);
            Route::post('tickets', [TicketController::class, 'store'])->middleware('permission:crear-ticket|gestionar-tickets');
            Route::get('tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->middleware($verTickets);
            Route::post('tickets/{ticket}/asignar', [TicketController::class, 'asignar'])->middleware('permission:asignar-ticket');
            Route::middleware('permission:gestionar-tickets')->group(function () {
                foreach (['tomar' => 'tomar', 'pedir-informacion' => 'pedirInformacion', 'resolver' => 'resolver',
                    'escalar' => 'escalar', 'vincular-bien' => 'vincularBien'] as $paso => $metodo) {
                    Route::post("tickets/{ticket}/{$paso}", [TicketController::class, $metodo]);
                }
            });
            // Del solicitante; la policy mira que el ticket sea suyo. Anular,
            // también quien reparte el trabajo.
            Route::middleware($verTickets)->group(function () {
                Route::post('tickets/{ticket}/confirmar', [TicketController::class, 'confirmar']);
                Route::post('tickets/{ticket}/reabrir', [TicketController::class, 'reabrir']);
                Route::post('tickets/{ticket}/anular', [TicketController::class, 'anular']);
            });

            // La conversación y sus archivos.
            Route::middleware($verTickets)->group(function () {
                Route::get('tickets/{ticket}/comentarios', [ComentarioTicketController::class, 'index']);
                Route::post('tickets/{ticket}/comentarios', [ComentarioTicketController::class, 'store']);
                Route::get('tickets/{ticket}/comentarios/{comentario}/archivo', [ComentarioTicketController::class, 'archivo']);
            });

            // Categorías de los tickets (fase 3.5): no se borran, se desactivan.
            // Las áreas DTIC se retiraron: atienden las subunidades de TIC (D6).
            Route::get('categorias', [CategoriaTicketController::class, 'index'])->middleware('permission:configurar-sla');
            Route::post('categorias', [CategoriaTicketController::class, 'store'])->middleware('permission:configurar-sla');
            Route::put('categorias/{categoria}', [CategoriaTicketController::class, 'update'])->middleware('permission:configurar-sla');

            // Técnicos
            Route::apiResource('tecnicos', TecnicoDticController::class)
                ->middleware('permission:gestionar-tecnicos');
            Route::get('tecnicos/{tecnico}/carga-trabajo',
                [TecnicoDticController::class, 'cargaTrabajo'])
                ->middleware('permission:asignar-ticket|gestionar-tecnicos');

            // SLA
            Route::apiResource('slas', SlaController::class)
                ->middleware('permission:configurar-sla');

            // Base de conocimiento: la lee cualquiera. El controlador solo
            // tiene `index` y `show`; el resto del recurso daba 500.
            Route::apiResource('base-conocimiento',
                BaseConocimientoController::class)
                ->only(['index', 'show']);

            // Encuestas de satisfacción
            $verEncuestas = 'permission:ver-tickets-todos|ver-dashboard-ejecutivo';

            Route::get('encuestas-satisfaccion',
                [EncuestaSatisfaccionController::class, 'index'])
                ->middleware($verEncuestas);

            // «resultados» antes del comodín, o encajaba en `{encuesta}` y
            // acababa en `show()` con esa palabra por id: la ruta era
            // inalcanzable y devolvía un 500.
            Route::get('encuestas-satisfaccion/resultados',
                [EncuestaSatisfaccionController::class, 'resultados'])
                ->middleware($verEncuestas);

            Route::get('encuestas-satisfaccion/{encuesta}',
                [EncuestaSatisfaccionController::class, 'show'])
                ->whereNumber('encuesta')
                ->middleware($verEncuestas);
        });

    // Módulo 08: Evaluación del Desempeño
    Route::prefix('evaluacion')->group(function () {
        Route::post('evaluaciones/{evaluacionId}/servidor/{servidorId}', [EvaluacionController::class, 'registrarResultado']);
    });

    // Reporte de apoyo a transcripción manual SIITH/SUT (no es integración
    // con API externa: SIITH/SUT no la tienen, se transcribe a mano en su
    // portal — este módulo solo estructura los datos para minimizar error
    // de transcripción).
    //
    // Desde la fase 7.5, por clase y con la marca de transcrito: lo ve y lo
    // marca Talento Humano; qué se reporta lo decide el Director.
    Route::prefix('reportes/siith-sut')
        ->middleware('permission:preparar-accion-personal|registrar-accion-personal')
        ->group(function () {
            Route::get('/', [ReporteSiithSutController::class, 'index']);
            Route::get('excel', [ReporteSiithSutController::class, 'excel']);
            Route::post('transcribir', [ReporteSiithSutController::class, 'transcribir']);
            Route::get('configuracion', [ReporteSiithSutController::class, 'configuracion']);
            Route::patch('configuracion/{clase}', [ReporteSiithSutController::class, 'actualizarConfiguracion'])
                ->middleware('permission:registrar-accion-personal');
        });

    // Módulo 09: Viáticos
    Route::prefix('viaticos')->group(function () {
        Route::get('/', [ViaticoController::class, 'index']);
        Route::post('/', [ViaticoController::class, 'store']);

        // Catálogos de viáticos
        Route::get('catalogos/tipos-transporte',
            [CatalogoViaticoController::class, 'tiposTransporte']);
        Route::get('catalogos/empresas/{tipoId}',
            [CatalogoViaticoController::class, 'empresasPorTipo']);
        Route::get('catalogos/categorias-factura',
            [CatalogoViaticoController::class, 'categoriasFactura']);

        // Bandeja de Financiero. Antes del comodín `{id}`.
        Route::get('bandeja', [BandejaViaticoController::class, 'index']);
        Route::get('bandeja/resumen', [BandejaViaticoController::class, 'resumen']);

        Route::prefix('vuelos')->group(function () {
            Route::get('/', [AutorizacionVueloController::class, 'index']);
            Route::post('{id}/aprobar', [AutorizacionVueloController::class, 'aprobar']);
            Route::post('{id}/rechazar', [AutorizacionVueloController::class, 'rechazar']);
            Route::post('{id}/documento', [AutorizacionVueloController::class, 'subirDocumento']);
        });

        Route::get('{id}', [ViaticoController::class, 'show']);
        Route::patch('{id}', [ViaticoController::class, 'update']);

        // A nombre de otro servidor: solo quien opera los viáticos. El propio
        // va por `POST /viaticos`. Había además un `{id}/solicitar` que pasaba
        // el id del viático como si fuera el del servidor: se retiró.
        Route::post('servidor/{servidorId}/solicitar', [ViaticoController::class, 'solicitar'])
            ->name('viatico.solicitar.por.servidor');
        Route::post('{id}/aprobar', [ViaticoController::class, 'aprobar']);

        Route::post('{id}/entregar-anticipo',
            [ViaticoController::class,
                'entregarAnticipo']);

        Route::post('{id}/marcar-en-comision',
            [ViaticoController::class,
                'marcarEnComision']);

        Route::post('{id}/cancelar',
            [ViaticoController::class,
                'cancelar']
        )->name('viaticos.cancelar');

        Route::post('{id}/rechazar',
            [ViaticoController::class,
                'rechazar']
        )->name('viaticos.rechazar');

        Route::post('{id}/devolver-correccion',
            [ViaticoController::class,
                'devolverCorreccion']
        )->name('viaticos.devolver-correccion');

        Route::post('{id}/marcar-pendiente-liquidacion',
            [ViaticoController::class,
                'marcarPendienteLiquidacion']);

        Route::post('{id}/contabilizar',
            [ViaticoController::class,
                'contabilizar']);

        Route::prefix('{viaticoId}/liquidacion')
            ->group(function () {
                Route::get('/',
                    [LiquidacionViaticoController::class,
                        'obtener']);

                Route::get('actividades',
                    [LiquidacionViaticoController::class,
                        'listarActividades']);
                Route::post('actividades',
                    [LiquidacionViaticoController::class,
                        'guardarActividades']);

                Route::get('facturas',
                    [LiquidacionViaticoController::class,
                        'listarFacturas']);
                Route::post('facturas',
                    [LiquidacionViaticoController::class,
                        'guardarFacturas']);
                Route::post('facturas/{factura}/revision',
                    [LiquidacionViaticoController::class,
                        'revisarFactura'])->whereNumber('factura');

                Route::post('confirmar',
                    [LiquidacionViaticoController::class,
                        'confirmar']);
            });

        Route::post('{viaticoId}/liquidar', [ViaticoController::class, 'liquidar']);

        Route::get('{identificador}/solicitud/generar-enlace',
            [InformeViaticoController::class, 'generarSolicitud']
        )->name('viaticos.solicitud.generar-enlace');

        Route::get('{identificador}/informe/generar-enlace',
            [InformeViaticoController::class, 'generarEnlace']
        )->name('viaticos.informe.generar-enlace');

        Route::get(
            '{identificador}/comprobante/generar',
            [InformeViaticoController::class,
                'generarComprobanteContabilidad']
        )->name('viaticos.comprobante.generar');

        // Tramos del itinerario
        Route::get('{viaticoId}/tramos',
            [TramoViaticoController::class, 'index']);
        Route::post('{viaticoId}/tramos',
            [TramoViaticoController::class, 'store']);
        Route::put('{viaticoId}/tramos/{tramo}',
            [TramoViaticoController::class, 'update']);
        Route::delete('{viaticoId}/tramos/{tramo}',
            [TramoViaticoController::class, 'destroy']);
    });

    // Módulo 14: Disciplinario — sumario administrativo (LOSEP), procedimiento
    // del reglamento interno y visto bueno (Código del Trabajo). Son
    // procedimientos distintos según el régimen del servidor, no dos nombres
    // para lo mismo.
    //
    // Sin `show` (2026-10-04): los cajones de detalle se arman con lo que trae
    // el listado, y lo único que añadía eran createdBy/updatedBy, que en el JSON
    // pisaban la columna `created_by` con el usuario entero de quien abrió el
    // trámite —su correo y su ficha, con cédula y teléfono—.
    Route::prefix('disciplinario')
        ->middleware('role:admin-uath')
        ->group(function () {
            Route::get('sumarios', [DisciplinarioController::class, 'index']);
            Route::post('sumarios', [DisciplinarioController::class, 'store']);
            Route::put('sumarios/{sumario}/avanzar', [DisciplinarioController::class, 'avanzar']);
            Route::post('sumarios/{id}/resolver', [DisciplinarioController::class, 'resolver']);

            // El catálogo de faltas del reglamento interno de los obreros (fase
            // 4.4). Sin borrado: se desactiva.
            Route::get('reglamento-interno/faltas', [FaltaReglamentoInternoController::class, 'index']);
            Route::post('reglamento-interno/faltas', [FaltaReglamentoInternoController::class, 'store']);
            Route::put('reglamento-interno/faltas/{falta}', [FaltaReglamentoInternoController::class, 'update']);

            Route::get('vistos-buenos', [VistoBuenoController::class, 'index']);
            Route::post('vistos-buenos', [VistoBuenoController::class, 'store']);
            Route::put('vistos-buenos/{vistoBueno}/transicionar', [VistoBuenoController::class, 'transicionar']);
            // La resolución del Inspector en PDF (2026-10-04).
            Route::post('vistos-buenos/{vistoBueno}/documento', [VistoBuenoController::class, 'adjuntarDocumento']);
            Route::get('vistos-buenos/{vistoBueno}/documento', [VistoBuenoController::class, 'descargarDocumento']);
        });

    // Módulo 10 — SSO
    Route::prefix('sso')
        ->middleware('role:admin-uath|asistente-uath|auditor')
        ->group(function () {
            Route::apiResource('riesgos', RiesgoLaboralController::class);
            Route::apiResource('accidentes', AccidenteTrabajoController::class);
            Route::apiResource('equipos-proteccion', EquipoProteccionController::class);
            Route::apiResource('inspecciones', InspeccionSsoController::class);
            Route::apiResource('capacitaciones', CapacitacionSsoController::class);

            Route::middleware('permission:ver-reportes-sso|gestionar-sso')->group(function () {
                Route::get('dashboard/resumen', [DashboardSsoController::class, 'resumen']);
                Route::get('factores-riesgo', [FactorRiesgoCatalogoController::class, 'index']);

                // `catalogos/equipos-proteccion` y no `equipos-proteccion/catalogo`:
                // el `apiResource('equipos-proteccion')` de arriba ya registró
                // `GET equipos-proteccion/{id}`, y Laravel resuelve por orden
                // de registro, así que el segundo nombre quedaría capturado
                // como un id. Un prefijo distinto no depende del orden del
                // archivo.
                Route::get('catalogos/equipos-proteccion', [EquipoProteccionController::class, 'catalogo']);
                Route::get('puestos/{puestoId}/equipos-proteccion', [PuestoEppController::class, 'index']);
                Route::get('epp-entregas/reporte', [EppEntregaController::class, 'reporte']);
                Route::get('epp-entregas', [EppEntregaController::class, 'index']);
                Route::get('servidores/{servidorId}/kit-epp', [EppEntregaController::class, 'kitParaServidor']);
                Route::get('horas-trabajadas', [HorasTrabajadasController::class, 'index']);
                Route::get('indicadores/reactivos', [IndicadoresSsoController::class, 'reactivos']);
                Route::get('indicadores/proactivos', [IndicadoresSsoController::class, 'proactivos']);
                Route::get('normativa-legal', [NormativaLegalSsoController::class, 'index']);
                Route::get('cumplimiento/lista-verificacion', [CumplimientoNormativaController::class, 'listaVerificacion']);
                Route::get('psicosocial/campanias', [EvaluacionPsicosocialController::class, 'index']);
                Route::get('psicosocial/campanias/{id}/resultados', [EvaluacionPsicosocialController::class, 'resultados']);
                Route::get('assist/campanias', [EvaluacionAssistController::class, 'index']);
                Route::get('assist/campanias/{id}/resultados', [EvaluacionAssistController::class, 'resultados']);
                Route::get('programa-drogas/actividades', [ProgramaDrogaActividadController::class, 'index']);
                Route::get('programa-drogas/seguimiento/lista', [ProgramaDrogaSeguimientoController::class, 'listaSeguimiento']);
                Route::get('documentos', [DocumentoSsoController::class, 'index']);
                Route::get('documentos/{id}/generar-enlace', [DocumentoSsoController::class, 'generarEnlace']);
            });

            Route::middleware('permission:gestionar-sso')->group(function () {
                Route::post('factores-riesgo', [FactorRiesgoCatalogoController::class, 'store']);
                Route::put('factores-riesgo/{id}', [FactorRiesgoCatalogoController::class, 'update']);
                Route::delete('factores-riesgo/{id}', [FactorRiesgoCatalogoController::class, 'destroy']);

                Route::post('puestos/{puestoId}/equipos-proteccion', [PuestoEppController::class, 'store']);
                Route::delete('puestos/{puestoId}/equipos-proteccion/{id}', [PuestoEppController::class, 'destroy']);

                Route::post('epp-entregas/kit', [EppEntregaController::class, 'storeKit']);
                Route::post('epp-entregas', [EppEntregaController::class, 'store']);

                Route::post('horas-trabajadas', [HorasTrabajadasController::class, 'store']);
                // Sin PUT a propósito: `registrarHorasTrabajadas` rechaza el
                // duplicado porque ese total es el denominador de los tres
                // índices del CD 513, y pisarlo movía los tres sin dejar
                // rastro. El PUT hacía exactamente eso, y no lo llamaba
                // ninguna pantalla. Para corregir un período se borra y se
                // vuelve a cargar, que sí es una decisión deliberada.
                Route::delete('horas-trabajadas/{id}', [HorasTrabajadasController::class, 'destroy']);

                Route::post('normativa-legal', [NormativaLegalSsoController::class, 'store']);
                Route::put('normativa-legal/{id}', [NormativaLegalSsoController::class, 'update']);
                Route::delete('normativa-legal/{id}', [NormativaLegalSsoController::class, 'destroy']);

                Route::post('cumplimiento', [CumplimientoNormativaController::class, 'store']);

                Route::post('psicosocial/campanias', [EvaluacionPsicosocialController::class, 'store']);
                Route::patch('psicosocial/campanias/{id}/cerrar', [EvaluacionPsicosocialController::class, 'cerrar']);

                Route::post('assist/campanias', [EvaluacionAssistController::class, 'store']);
                Route::patch('assist/campanias/{id}/cerrar', [EvaluacionAssistController::class, 'cerrar']);

                Route::post('programa-drogas/actividades', [ProgramaDrogaActividadController::class, 'store']);
                Route::put('programa-drogas/actividades/{id}', [ProgramaDrogaActividadController::class, 'update']);
                Route::delete('programa-drogas/actividades/{id}', [ProgramaDrogaActividadController::class, 'destroy']);

                Route::post('programa-drogas/seguimiento', [ProgramaDrogaSeguimientoController::class, 'store']);

                Route::post('documentos', [DocumentoSsoController::class, 'store']);
                Route::delete('documentos/{id}', [DocumentoSsoController::class, 'destroy']);
            });
        });

    // Módulo 11 — Dispensario Médico
    Route::prefix('dispensario')->group(function () {

        // Agenda — accesible para todo el personal del dispensario, y solo
        // para él. El comentario lo decía, pero estas seis rutas no llevaban
        // `role:` y cualquier servidor con sesión podía ver la cola o marcar
        // a un paciente como no presentado.
        Route::middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('agenda/turnos-del-dia',
                    [AgendaController::class, 'turnosDelDia']
                )->name('agenda.turnosDelDia');
                Route::patch('agenda/{agenda}/no-presentado',
                    [AgendaController::class, 'noPresentado']
                )->name('agenda.noPresentado');
                Route::patch('agenda/{agenda}/reactivar',
                    [AgendaController::class, 'reactivar']
                )->name('agenda.reactivar');
                Route::patch('agenda/{agenda}/en-consulta',
                    [AgendaController::class, 'marcarEnConsulta']
                )->name('agenda.enConsulta');
                // «Finalizar» sin consulta: el turno vuelve a la cola en vez
                // de quedarse en consulta para siempre. Es de quien atiende.
                Route::patch('agenda/{agenda}/devolver-a-la-cola',
                    [AgendaController::class, 'devolverALaCola']
                )->middleware('role:medico|odontologo')
                 ->name('agenda.devolverALaCola');
                Route::get('agenda/por-folio/{folio}',
                    [AgendaController::class, 'porFolio']
                )->name('agenda.porFolio');

                Route::get('triaje/pendientes',
                    [TriajeController::class, 'pendientes']
                )->name('dispensario.triaje.pendientes');
            });

        // Sin `update`: era un PUT que pasaba `$request->all()` a `update()` —
        // estado, folio, paciente—, y el frontend nunca lo usó.
        Route::apiResource('agenda', AgendaController::class)
            ->except(['update'])
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario');

        // Triaje
        Route::prefix('agenda/{agendaId}/triaje')->group(function () {
            Route::post('/', [TriajeController::class, 'store'])
                ->middleware('role:enfermera|admin-dispensario');
            Route::get('/', [TriajeController::class, 'show'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::get('ultimo', [TriajeController::class, 'ultimoPorAgenda'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::get('historial', [TriajeController::class, 'historial'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
        });

        // Dashboard Estadístico — SOLO admin-dispensario. La máxima autoridad
        // salió con su rol el 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7).
        Route::get('dashboard/kpis', [DashboardDispensarioController::class, 'kpis'])
            ->middleware('role:admin-dispensario');
        Route::get('dashboard/panorama', [DashboardDispensarioController::class, 'panorama'])
            ->middleware('role:admin-dispensario');

        // «Mi jornada»: cada profesional ve solo lo suyo. No es el tablero de
        // la jefatura, que compara a unos con otros.
        Route::get('mi-jornada', [MiJornadaController::class, 'index'])
            ->middleware('role:medico|odontologo|enfermera');

        // Reportes del Dispensario. Qué reporte y con qué alcance lo decide
        // `AlcanceReporte`: la administración todo y cada profesional lo suyo.
        Route::prefix('reportes')
            ->middleware('role:admin-dispensario|medico|odontologo|enfermera')
            ->group(function () {
                Route::get('/', [ReporteDispensarioController::class, 'index']);
                Route::get('{clave}/excel', [ReporteDispensarioController::class, 'excel']);
                Route::get('{clave}/pdf', [ReporteDispensarioController::class, 'pdf']);
                Route::get('{clave}', [ReporteDispensarioController::class, 'show']);
            });

        // El personal clínico del dispensario, con nombre: lo piden Farmacia
        // (quién recetó) y la asignación de turnos. No es para cualquiera con
        // sesión.
        Route::get('personal-medico',
            [PersonalMedicoController::class, 'index']
        )->middleware('role:medico|odontologo|enfermera|admin-dispensario')
         ->name('dispensario.personal-medico');

        // Autocompletado del CIE-10 al diagnosticar (consulta, FEMO y
        // certificado). Estaba entre las rutas públicas, sin sesión siquiera:
        // el catálogo no es secreto, pero cada letra era una consulta de texto
        // sobre 8918 filas que cualquiera podía lanzar desde fuera.
        Route::get('cie10/buscar', [DiagnosticoCie10Controller::class, 'buscar'])
            ->middleware('role:medico|odontologo|admin-dispensario')
            ->name('dispensario.cie10.buscar');

        // A quién asignarle un turno. Lo pide Recepción, así que va con el
        // acceso general del dispensario y no con el rol clínico.
        Route::get('disponibilidad/personal',
            [DisponibilidadController::class, 'paraAtencion']
        )->middleware('role:medico|odontologo|enfermera|admin-dispensario')
         ->name('dispensario.disponibilidad.personal');

        // Marcarse disponible es de quien atiende pacientes. La guarda existía
        // solo en la pantalla, que ocultaba el control: por la API cualquiera
        // del dispensario podía marcarse disponible, y aunque la lista de
        // disponibles filtra por rol al leer, la fila quedaba escrita igual.
        Route::get('disponibilidad/mi-estado',
            [DisponibilidadController::class, 'miEstado']
        )->middleware('role:medico|odontologo')
         ->name('dispensario.disponibilidad.miEstado');

        Route::post('disponibilidad/alternar',
            [DisponibilidadController::class, 'alternar']
        )->middleware('role:medico|odontologo')
         ->name('dispensario.disponibilidad.alternar');

        // Buscar por cédula revela si alguien es paciente del dispensario, y
        // las atenciones de enfermería son datos de salud: ni una cosa ni la
        // otra se abre a cualquier servidor con sesión.
        Route::get('pacientes/buscar',
            [PacienteController::class, 'buscar']
        )->middleware('role:medico|odontologo|enfermera|admin-dispensario')
         ->name('dispensario.pacientes.buscar');

        Route::get('pacientes/buscar-por-nombre',
            [PacienteController::class, 'buscarPorNombre']
        )->middleware('role:medico|odontologo|enfermera|admin-dispensario')
         ->name('dispensario.pacientes.buscar-por-nombre');

        Route::get('atenciones-enfermeria',
            [AtencionEnfermeriaController::class, 'index']
        )->middleware('role:medico|odontologo|enfermera|admin-dispensario')
         ->name('dispensario.atenciones-enfermeria.index');

        // Registrar y anular un servicio es trabajo de Enfermería.
        Route::middleware('role:enfermera|admin-dispensario')->group(function () {
            Route::post('atenciones-enfermeria',
                [AtencionEnfermeriaController::class, 'store']
            )->name('dispensario.atenciones-enfermeria.store');

            Route::patch('atenciones-enfermeria/{id}/anular',
                [AtencionEnfermeriaController::class, 'anular']
            )->name('dispensario.atenciones-enfermeria.anular');

            Route::get('catalogo-servicios-enfermeria',
                [AtencionEnfermeriaController::class, 'catalogo']
            )->name('dispensario.catalogo-servicios-enfermeria');
        });

        // Historias clínicas — SOLO personal médico
        Route::prefix('historias-clinicas')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('/', [HistoriaClinicaController::class, 'index']);
                Route::post('/', [HistoriaClinicaController::class, 'store']);
                Route::get('buscar-por-cedula', [HistoriaClinicaController::class, 'buscarPorCedula']);
                Route::post('crear-por-cedula', [HistoriaClinicaController::class, 'crearPorCedula']);
                Route::get('{id}', [HistoriaClinicaController::class, 'show']);
                Route::get('{id}/contexto-consulta', [HistoriaClinicaController::class, 'contextoConsulta']);

                // Alergias
                Route::prefix('{id}/alergias')->group(function () {
                    Route::get('/', [AlergiaPacienteController::class, 'index']);
                    Route::post('/', [AlergiaPacienteController::class, 'store']);
                    Route::patch('{alergia}/anular',
                        [AlergiaPacienteController::class, 'anular']
                    )->middleware('role:medico|odontologo');
                });

                // Antecedentes
                Route::prefix('{id}/antecedentes')->group(function () {
                    Route::get('/', [AntecedentePacienteController::class, 'index']);
                    Route::post('/', [AntecedentePacienteController::class, 'store']);
                    Route::patch('{antecedente}/anular',
                        [AntecedentePacienteController::class, 'anular']
                    )->middleware('role:medico|odontologo');
                });
            });

        // Consultas médicas: las escriben y las leen el médico y el odontólogo.
        // Hasta el 2026-10-26 también las leían Enfermería y la administración
        // del Dispensario, que no las usan en ninguna pantalla; y quien las lee
        // ve solo las suyas y las de los pacientes que atiende
        // (ConsultaMedicaController; docs/plan-roles-y-accesos.md, PR 5).
        Route::prefix('consultas')
            ->middleware('role:medico|odontologo')
            ->group(function () {
                Route::get('/', [ConsultaMedicaController::class, 'index']);
                // Registrarla es de quien atiende. El comentario de arriba lo
                // decía, pero el alta solo heredaba el rol del grupo, y una
                // enfermera o la administración podían crear una consulta
                // firmada con su usuario.
                Route::post('/', [ConsultaMedicaController::class, 'store'])
                    ->middleware('role:medico|odontologo');

                // Lo que se lleva escrito y aún no se ha guardado. Antes del
                // comodín {id}, o «borrador» se leería como un id.
                Route::get('borrador',
                    [BorradorConsultaController::class, 'show']
                )->name('dispensario.consultas.borrador.show');
                Route::put('borrador',
                    [BorradorConsultaController::class, 'guardar']
                )->name('dispensario.consultas.borrador.guardar');
                Route::delete('borrador',
                    [BorradorConsultaController::class, 'destroy']
                )->name('dispensario.consultas.borrador.destroy');

                // Antes del comodín {id}, o «versiones» se leería como un id.
                Route::get('{id}/versiones',
                    [ConsultaMedicaController::class, 'versiones']
                );
                Route::get('{id}', [ConsultaMedicaController::class, 'show']);
                Route::patch('{id}', [ConsultaMedicaController::class, 'update'])
                    ->middleware('role:medico|odontologo');
            });

        // Odontograma — mismo acceso que consultas médicas
        // NOTA: segmentos literales (historia-clinica, procedimientos, piezas)
        // registrados antes de cualquier wildcard {id}.
        Route::prefix('odontograma')
            ->middleware('role:medico|odontologo|admin-dispensario')
            ->group(function () {
                Route::get('historia-clinica/{historiaClinicaId}', [OdontogramaController::class, 'show']);
                Route::get('piezas/{piezaId}/historial', [OdontogramaController::class, 'historialPieza']);
            });

        // Lo que se le hizo a cada pieza lo registra el odontólogo, y nadie
        // más: también lo podían registrar el médico y la administración del
        // Dispensario (docs/plan-roles-y-accesos.md, PR 4).
        Route::prefix('odontograma')
            ->middleware('role:odontologo')
            ->group(function () {
                Route::post('procedimientos', [OdontogramaController::class, 'registrarProcedimiento']);
                Route::patch('procedimientos/{id}/anular', [OdontogramaController::class, 'anularProcedimiento']);
            });

        // Certificados médicos: dicen el diagnóstico y los días de reposo de
        // un servidor, y solo «anular» pedía rol. Los emite quien atiende; los
        // consulta y descarga también la administración del Dispensario.
        Route::get('certificados-medicos',
            [CertificadoMedicoController::class, 'index']
        )->middleware('role:medico|odontologo|admin-dispensario')
         ->name('dispensario.certificados.index');

        Route::post('certificados-medicos',
            [CertificadoMedicoController::class, 'store']
        )->middleware('role:medico|odontologo')
         ->name('dispensario.certificados.store');

        // Antes del comodín {id}, o los segmentos literales se leerían como id.
        Route::get('certificados-medicos/{id}/pdf',
            [CertificadoMedicoController::class, 'pdf']
        )->middleware('role:medico|odontologo|admin-dispensario')
         ->name('dispensario.certificados.pdf');

        Route::patch('certificados-medicos/{id}/anular',
            [CertificadoMedicoController::class, 'anular']
        )->middleware('role:medico|odontologo|admin-dispensario')
         ->name('dispensario.certificados.anular');

        Route::get('certificados-medicos/{id}',
            [CertificadoMedicoController::class, 'show']
        )->middleware('role:medico|odontologo|admin-dispensario')
         ->name('dispensario.certificados.show');

        // Recetas — médicos emiten, enfermeras y admin despachan
        Route::prefix('recetas')->group(function () {
            Route::get('/', [RecetaController::class, 'index'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::post('/', [RecetaController::class, 'store'])
                ->middleware('role:medico|odontologo');
            // Antes del comodín {id}, o «pdf» se leería como parte de él.
            Route::get('{id}/pdf', [RecetaController::class, 'pdf'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
                ->name('dispensario.recetas.pdf');
            Route::get('{id}', [RecetaController::class, 'show'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::post('{id}/despachar', [RecetaController::class, 'despachar'])
                ->middleware('role:enfermera|admin-dispensario');
            // Lo que el mostrador no va a entregar: el paciente lo adquiere
            // fuera (decisión del Dispensario del 2026-10-24, opción A).
            Route::patch('{id}/items/{itemId}/no-entregado', [RecetaController::class, 'noEntregado'])
                ->middleware('role:enfermera|admin-dispensario')
                ->whereNumber(['id', 'itemId']);
            Route::post('{id}/anular', [RecetaController::class, 'anular'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::patch('{recetaId}/items/{itemId}',
                [ItemRecetaController::class, 'update']
            )->middleware('role:medico|odontologo');
            Route::delete('{recetaId}/items/{itemId}',
                [ItemRecetaController::class, 'destroy']
            )->middleware('role:medico|odontologo');
        });

        // Inventario — lo consulta todo el personal del Dispensario; lo que
        // cambia las existencias, el mostrador y la administración (decisión
        // del Dispensario del 2026-10-24, pregunta 2). Antes los cuatro roles
        // podían todo, ajustar el stock sin documento incluido.
        Route::prefix('inventario')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('medicinas/buscar',
                    [InventarioMedicinasController::class, 'buscar']);
                Route::get('medicinas/stock-bajo',
                    [InventarioMedicinasController::class, 'contarStockBajo']);
                Route::apiResource('medicinas', InventarioMedicinasController::class)
                    ->only(['index', 'show']);
                Route::get('medicinas/{id}/kardex',
                    [InventarioMedicinasController::class, 'kardex']);
                // Dar de alta, editar, retirar o reactivar un medicamento y
                // ajustar el stock: la administración.
                Route::apiResource('medicinas', InventarioMedicinasController::class)
                    ->only(['store', 'update', 'destroy'])
                    ->middleware('role:admin-dispensario');
                Route::post('medicinas/{medicina}/ajustar-inventario',
                    [InventarioMedicinasController::class, 'ajustarInventario'])
                    ->middleware('role:admin-dispensario');
                // Dar de baja existencias vencidas o rotas: también enfermería.
                Route::post('medicinas/{medicina}/baja',
                    [InventarioMedicinasController::class, 'registrarBaja'])
                    ->middleware('role:enfermera|admin-dispensario');
            });

        Route::prefix('adquisiciones')
            ->middleware('role:admin-dispensario')
            ->group(function () {
                Route::get('/', [AdquisicionController::class, 'index']);
                Route::post('/', [AdquisicionController::class, 'store']);
                Route::get('{id}', [AdquisicionController::class, 'show']);
                Route::post('{id}/documento', [AdquisicionController::class, 'subirDocumento']);
                Route::get('{id}/documento', [AdquisicionController::class, 'verDocumento']);
                Route::post('{id}/anular', [AdquisicionController::class, 'anular']);
            });

        // Signos vitales de la solicitud (enfermería, previo al FEMO)
        // NOTA: debe registrarse antes que GET {id} para que "pendientes-triaje"
        // no sea capturado por la ruta comodín {id}.
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:enfermera|admin-dispensario')
            ->group(function () {
                Route::get('pendientes-triaje', [SolicitudCertificacionController::class, 'pendientesTriaje']);
                Route::post('{id}/signos-vitales', [SolicitudCertificacionController::class, 'registrarSignosVitales']);
            });

        // Creación de solicitudes en lote desde Expediente (RRHH, no médico)
        // NOTA: debe registrarse antes que GET {id} de la ruta wildcard.
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:admin-uath|asistente-uath|admin-dispensario')
            ->group(function () {
                Route::post('lote', [SolicitudCertificacionController::class, 'storeLote']);
                // Quien la pidió la retira: cancelar no es un acto médico. El
                // controlador comprueba `solicitar-certificacion-medica`.
                Route::patch('{id}/cancelar', [SolicitudCertificacionController::class, 'cancelar']);
            });

        // El tablero de cobertura: una fila por servidor activo, no por
        // solicitud. Es el único sitio desde donde se ve a quien nunca tuvo
        // una evaluación, así que lo leen los mismos que el seguimiento.
        Route::prefix('certificaciones/cobertura')
            ->middleware('role:medico|admin-dispensario|admin-uath|asistente-uath')
            ->group(function () {
                Route::get('/', [CoberturaCertificacionController::class, 'index']);
                Route::get('excel', [CoberturaCertificacionController::class, 'excel']);
            });

        // Lectura del seguimiento: el médico trabaja sobre estas solicitudes y
        // Talento Humano las consulta.
        //
        // `asistente-uath` lleva `solicitar-certificacion-medica` desde el
        // seeder, y ese es justo el permiso con el que `nav.ts` pinta
        // «Certificaciones médicas»: sin el rol declarado aquí veía el ítem en
        // el menú y recibía un 403 al abrirlo. Lo mismo le pasaba con el lote
        // desde Expediente, que el controlador ya le autoriza por permiso.
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:medico|admin-dispensario|admin-uath|asistente-uath')
            ->group(function () {
                Route::get('/', [SolicitudCertificacionController::class, 'index']);
                // El certificado de aptitud: la aptitud, sus restricciones y
                // quién firma. Va con la lectura porque es justo el documento
                // que el expediente administrativo sí puede recibir, al revés
                // que el PDF del FEMO.
                Route::get('{id}/certificado-aptitud', [CertificadoAptitudController::class, 'generar']);
                Route::get('{id}', [SolicitudCertificacionController::class, 'show']);
            });

        // El acto médico. Iniciar el FEMO y cerrar la solicitud con un dictamen
        // de aptitud lo hace quien evalúa, y nadie más: iban en el mismo grupo
        // que la lectura, así que Talento Humano podía emitir el dictamen. Desde
        // el 2026-10-26 tampoco la administración del Dispensario, que no tiene
        // permisos clínicos; el dictamen, además, solo el médico de la ficha
        // (docs/plan-roles-y-accesos.md, PR 4).
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:medico')
            ->group(function () {
                Route::patch('{id}/iniciar', [SolicitudCertificacionController::class, 'iniciarProceso']);
                Route::patch('{id}/completar', [SolicitudCertificacionController::class, 'completar']);
            });

        // La incorporación del candidato la confirma Talento Humano
        // (`gestionar-onboarding`, que el controlador vuelve a comprobar): crea
        // el expediente del servidor y el borrador del ingreso, no es un acto
        // clínico.
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:admin-uath')
            ->group(function () {
                Route::post('{id}/confirmar-incorporacion', [SolicitudCertificacionController::class, 'confirmarIncorporacion']);
            });

        // Tablero de salud ocupacional: la bandeja en cifras, la aptitud
        // emitida, los diagnósticos y la exposición a factores de riesgo. Es
        // de quien evalúa, como las fichas.
        Route::get('salud-ocupacional/tablero', [TableroSaludOcupacionalController::class, 'index'])
            ->middleware('role:medico|admin-dispensario');

        // Fichas de salud ocupacional
        Route::prefix('fichas-sso')
            ->middleware('role:medico|admin-dispensario')
            ->group(function () {
                // Catálogo oficial de factores de riesgo del MSP (sección G).
                // Va antes de '{id}' para que 'catalogo-riesgos' no se lea como id.
                Route::get('catalogo-riesgos', [FichaSaludOcupacionalController::class, 'catalogoRiesgos']);
                // Lo que la sección J puede traer de los resultados del paciente.
                Route::get('resultados-del-paciente', [FemoResultadosController::class, 'index']);
                // Qué exámenes pide cada factor de riesgo del puesto (D6).
                Route::get('examenes-por-riesgo', [ExamenesPorRiesgoController::class, 'index']);
                Route::put('examenes-por-riesgo', [ExamenesPorRiesgoController::class, 'update']);
                Route::get('/', [FichaSaludOcupacionalController::class, 'index']);
                Route::get('{id}', [FichaSaludOcupacionalController::class, 'show']);
                Route::get('{id}/pdf', [FemoPdfController::class, 'generar']);
            });

        // Llenar la ficha es el acto médico: solo el médico, y una ficha en
        // curso solo la continúa quien la creó (lo comprueba el controlador).
        // La administración del Dispensario la consulta, arriba
        // (docs/plan-roles-y-accesos.md, PR 4).
        Route::prefix('fichas-sso')
            ->middleware('role:medico')
            ->group(function () {
                Route::post('/', [FichaSaludOcupacionalController::class, 'store']);
                Route::patch('{id}', [FichaSaludOcupacionalController::class, 'update']);
            });

        // Exámenes: resultados, catálogo, perfiles, bandeja, envíos del Portal
        // y órdenes. Enfermería no entra a nada de esto desde el 2026-10-24:
        // no registra, no valida ni hace el seguimiento que justificaba que
        // viera la bandeja, y lo que veía eran resultados y nombres de
        // exámenes —VIH, VDRL— de cada paciente. Decisión del usuario.

        // Resultados médicos
        Route::prefix('resultados-medicos')
            ->middleware('role:medico|odontologo|admin-dispensario')
            ->group(function () {
                Route::get('/', [ResultadoMedicoController::class, 'index']);
                // Los registran el médico y el odontólogo (D2 y P1 de
                // docs/diseno-examenes-medicos.md).
                Route::post('/', [ResultadoMedicoController::class, 'store'])
                    ->middleware('role:medico|odontologo');
                // El archivo ya no se sirve desde /storage: sale por aquí.
                Route::get('{id}/archivo', [ResultadoMedicoController::class, 'archivo'])
                    ->whereNumber('id');
                Route::delete('{id}', [ResultadoMedicoController::class, 'destroy'])
                    ->middleware('role:medico|odontologo|admin-dispensario');
                // La lectura del médico (D3): normal, alterado o crítico; y
                // quien pidió el examen da por visto un crítico ajeno.
                Route::patch('{id}/revisar', [RevisionResultadoController::class, 'revisar'])
                    ->middleware('role:medico|odontologo')
                    ->whereNumber('id');
                Route::patch('{id}/enterado', [RevisionResultadoController::class, 'enterado'])
                    ->middleware('role:medico|odontologo')
                    ->whereNumber('id');
            });

        // Catálogo de exámenes (docs/diseno-examenes-medicos.md, fase 1): lo
        // lee el personal clínico para pedir; lo mantiene la administración.
        Route::prefix('catalogo-examenes')->group(function () {
            Route::get('/', [CatalogoExamenController::class, 'index'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::post('/', [CatalogoExamenController::class, 'store'])
                ->middleware('role:admin-dispensario');
            Route::patch('{examen}', [CatalogoExamenController::class, 'update'])
                ->middleware('role:admin-dispensario')
                ->whereNumber('examen');
        });

        // Perfiles de exámenes: los arman el médico, el odontólogo (los de su
        // especialidad) y la administración (D6, D12).
        Route::prefix('perfiles-examen')->group(function () {
            Route::get('/', [PerfilExamenController::class, 'index'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::post('/', [PerfilExamenController::class, 'store'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::patch('{perfil}', [PerfilExamenController::class, 'update'])
                ->middleware('role:medico|odontologo|admin-dispensario')
                ->whereNumber('perfil');
        });

        // La bandeja: las órdenes que esperan resultados.
        Route::get('examenes/bandeja', [BandejaExamenesController::class, 'index'])
            ->middleware('role:medico|odontologo|admin-dispensario');
        Route::get('examenes/bandeja/por-revisar', [BandejaExamenesController::class, 'porRevisar'])
            ->middleware('role:medico|odontologo|admin-dispensario');

        // Órdenes de exámenes: las emite el médico o el odontólogo en su
        // consulta; las anula quien las emitió o la administración.
        Route::prefix('ordenes-examen')->group(function () {
            Route::get('/', [OrdenExamenController::class, 'index'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::post('/', [OrdenExamenController::class, 'store'])
                ->middleware('role:medico|odontologo');
            Route::get('{orden}', [OrdenExamenController::class, 'show'])
                ->middleware('role:medico|odontologo|admin-dispensario')
                ->whereNumber('orden');
            Route::get('{orden}/pdf', [OrdenExamenController::class, 'pdf'])
                ->middleware('role:medico|odontologo|admin-dispensario')
                ->whereNumber('orden')
                ->name('dispensario.ordenes-examen.pdf');
            Route::patch('{orden}/anular', [OrdenExamenController::class, 'anular'])
                ->middleware('role:medico|odontologo|admin-dispensario')
                ->whereNumber('orden');
            // Lo marca quien registra resultados (D2, P1).
            Route::patch('{orden}/items/{item}/no-realizado', [OrdenExamenController::class, 'noRealizado'])
                ->middleware('role:medico|odontologo')
                ->whereNumber(['orden', 'item']);
        });
    });

    // Admin TI
    //
    // El grupo estaba cerrado con 'role:admin-ti|admin-uath'. Esa lista de
    // roles no coincidía con los permisos que exige la policy: admin-uath
    // entraba al grupo pero no tiene 'gestionar-usuarios', así que recibía 403
    // en el listado y, a la vez, alcanzaba los endpoints que entonces no
    // comprobaban nada —podía auto-otorgarse cualquier permiso del sistema.
    //
    // Ahora cada acción se autoriza por su permiso concreto en UsuarioPolicy,
    // que es la única lista que hay que mantener.
    Route::prefix('admin')
        ->group(function () {
            // Antes que apiResource: si no, 'usuarios/{usuario}' se traga la
            // ruta literal.
            Route::get('usuarios/sugerir-usuario-ti',
                [UsuarioController::class, 'sugerirUsuarioTi'])
                ->name('usuarios.sugerirUsuarioTi');

            Route::post('usuarios/{id}/toggle-activo',
                [UsuarioController::class, 'toggleActivo'])
                ->name('usuarios.toggleActivo');

            Route::post('usuarios/{id}/desvincular-servidor',
                [UsuarioController::class, 'desvincularServidor'])
                ->name('usuarios.desvincularServidor');

            Route::post('usuarios/{id}/asignar-servidor',
                [UsuarioController::class, 'asignarServidor'])
                ->name('usuarios.asignarServidor');

            Route::get('usuarios-roles',
                [UsuarioController::class, 'roles'])
                ->name('usuarios.roles');
            Route::apiResource('usuarios', UsuarioController::class);
            Route::post('usuarios/{id}/restablecer-contrasena',
                [UsuarioController::class, 'restablecerContrasena']
            )->name('usuarios.restablecerContrasena');

            Route::get('permisos',
                [PermisoController::class, 'index'])
                ->name('admin.permisos.index');

            Route::get('usuarios/{id}/permisos',
                [PermisoController::class, 'permisosUsuario'])
                ->name('admin.usuarios.permisos');

            Route::post('usuarios/{id}/permisos',
                [PermisoController::class, 'sincronizarPermisosUsuario'])
                ->name('admin.usuarios.permisos.sincronizar');
        });

    // Módulo 15 — Capacitación
    Route::prefix('capacitacion')
        ->group(function () {
            Route::apiResource('planes', PlanCapacitacionController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::apiResource('cursos', CursoController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::post('cursos/{curso}/inscribir',
                [CursoController::class, 'inscribir']);
            Route::post('cursos/{curso}/evaluar',
                [CursoController::class, 'evaluar'])
                ->middleware('role:admin-uath|asistente-uath');
            Route::get('certificados/{servidorId}',
                [CertificadoCapacitacionController::class, 'show']);
            Route::post('certificados/{inscripcionId}/generar',
                [CertificadoCapacitacionController::class, 'generar'])
                ->middleware('role:admin-uath|asistente-uath');
        });

    // Módulo 16 — Actividades Laborales: viven en el portal, en
    // `autoservicio/mis-actividades`. Aquí había un CRUD sin dueño —cualquiera
    // leía, editaba o borraba las de otro— y un «informe» que devolvía una
    // ruta a un PDF que nunca se generaba; ninguna pantalla lo usó.

    // Módulo 17 — Bienestar y Clima
    Route::prefix('bienestar')
        ->group(function () {
            Route::apiResource('planes', PlanBienestarController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::get('encuestas',
                [EncuestaClimaController::class, 'index']);
            Route::post('encuestas',
                [EncuestaClimaController::class, 'store'])
                ->middleware('role:admin-uath');
            Route::post('encuestas/{encuesta}/responder',
                [EncuestaClimaController::class, 'responder']);
            Route::get('encuestas/{encuesta}/resultados',
                [EncuestaClimaController::class, 'resultados'])
                ->middleware('role:admin-uath|director');
        });

    // Comunicación Social: las fotografías de los servidores
    // (docs/diseno-comunicacion-social.md, fase 1.1).
    Route::prefix('comunicacion')->group(function () {
        // El Inicio (fase 3.3): entra quien toma las fotos, consulta las
        // credenciales o cambia los logos; cada bloque sale con su permiso.
        Route::get('tablero', TableroComunicacionController::class)
            ->middleware('permission:gestionar-fotografias|ver-credenciales|gestionar-identidad-institucional');

        Route::middleware('permission:gestionar-fotografias')->group(function () {
            Route::get('fotografias', [FotografiaServidorController::class, 'index']);
            Route::post('fotografias/carga/simular', [CargaFotografiasController::class, 'simular']);
            Route::post('fotografias/carga', [CargaFotografiasController::class, 'cargar']);
            Route::post('fotografias/{fotografia}/restaurar', [FotografiaServidorController::class, 'restaurar'])
                ->whereNumber('fotografia');
            Route::get('servidores/{servidor}/fotografias', [FotografiaServidorController::class, 'historial'])
                ->whereNumber('servidor');
            Route::post('servidores/{servidor}/fotografia', [FotografiaServidorController::class, 'store'])
                ->whereNumber('servidor');
            Route::delete('servidores/{servidor}/fotografia', [FotografiaServidorController::class, 'retirar'])
                ->whereNumber('servidor');
        });

        // Las credenciales (fase 3.1): consultarlas, Comunicación, Talento
        // Humano y Auditoría; imprimir, revocar y recibir la tarjeta, solo
        // Comunicación. No se emiten: nacen con la foto y el vínculo vigente.
        Route::middleware('permission:ver-credenciales')->group(function () {
            Route::get('credenciales', [CredencialController::class, 'index']);
            Route::get('credenciales/verificacion', [CredencialController::class, 'verificacion']);
            Route::get('servidores/{servidor}/credenciales', [CredencialController::class, 'historial'])
                ->whereNumber('servidor');
        });
        Route::middleware('permission:gestionar-credenciales')->group(function () {
            Route::post('credenciales/imprimir', [CredencialController::class, 'imprimir']);
            Route::post('credenciales/{credencial}/revocar', [CredencialController::class, 'revocar'])
                ->whereNumber('credencial');
            Route::post('credenciales/{credencial}/devolucion', [CredencialController::class, 'devolucion'])
                ->whereNumber('credencial');
        });

        // La imagen la ve también quien puede ver al servidor o consulta las
        // credenciales: lo decide FotografiaServidorPolicy, no un permiso.
        Route::get('fotografias/{fotografia}/imagen', [FotografiaServidorController::class, 'imagen'])
            ->whereNumber('fotografia');

        // La identidad institucional (fase 2.1): solo la persona designada
        // para eso, con el rol `responsable-identidad` (D2).
        Route::prefix('identidad')->middleware('permission:gestionar-identidad-institucional')->group(function () {
            Route::get('/', [IdentidadInstitucionalController::class, 'index']);
            Route::put('datos', [IdentidadInstitucionalController::class, 'actualizarDatos']);
            Route::get('versiones/{recurso}/archivo', [IdentidadInstitucionalController::class, 'archivo'])
                ->whereNumber('recurso');
            Route::post('versiones/{recurso}/reutilizar', [IdentidadInstitucionalController::class, 'reutilizar'])
                ->whereNumber('recurso');
            Route::delete('versiones/{recurso}', [IdentidadInstitucionalController::class, 'anular'])
                ->whereNumber('recurso');
            Route::get('{variante}/versiones', [IdentidadInstitucionalController::class, 'versiones']);
            Route::post('{variante}', [IdentidadInstitucionalController::class, 'store']);
        });
    });

    // Noticias y comunicados de Talento Humano
    // (docs/diseno-noticias-comunicados.md, 7.1). Redacta asistente-uath y
    // admin-uath; publica admin-uath, y un asistente solo con el permiso
    // adicional (D1). Lo que depende del estado lo decide ComunicadoService.
    // Los servidores los leerán por `autoservicio/comunicados`.
    Route::prefix('comunicados')->group(function () {
        Route::middleware('permission:redactar-comunicados|publicar-comunicados|ver-lecturas-comunicados')->group(function () {
            Route::get('/', [ComunicadoController::class, 'index'])->name('comunicados.index');
            Route::get('{comunicado}', [ComunicadoController::class, 'show'])->whereNumber('comunicado');
            Route::get('{comunicado}/adjuntos/{adjunto}', [ArchivosComunicadoController::class, 'adjunto'])
                ->whereNumber(['comunicado', 'adjunto'])
                ->scopeBindings();
        });

        Route::middleware('permission:redactar-comunicados')->group(function () {
            Route::post('/', [ComunicadoController::class, 'store']);
            Route::post('destinatarios/conteo', [ComunicadoController::class, 'contarDestinatarios']);
            Route::put('{comunicado}', [ComunicadoController::class, 'update'])->whereNumber('comunicado');
            Route::delete('{comunicado}', [ComunicadoController::class, 'destroy'])->whereNumber('comunicado');
            Route::post('{comunicado}/duplicar', [ComunicadoController::class, 'duplicar'])->whereNumber('comunicado');
            Route::post('{comunicado}/adjuntos', [ArchivosComunicadoController::class, 'agregarAdjunto'])->whereNumber('comunicado');
            Route::delete('{comunicado}/adjuntos/{adjunto}', [ArchivosComunicadoController::class, 'quitarAdjunto'])
                ->whereNumber(['comunicado', 'adjunto'])
                ->scopeBindings();
        });

        Route::middleware('permission:publicar-comunicados')->group(function () {
            Route::post('{comunicado}/publicar', [ComunicadoController::class, 'publicar'])->whereNumber('comunicado');
            Route::post('{comunicado}/retirar', [ComunicadoController::class, 'retirar'])->whereNumber('comunicado');
            Route::patch('{comunicado}/publicacion', [ComunicadoController::class, 'actualizarPublicacion'])->whereNumber('comunicado');
        });

        // El seguimiento (6.4): quién leyó, quién confirmó y el Excel de quién falta.
        Route::middleware('permission:ver-lecturas-comunicados')->group(function () {
            Route::get('{comunicado}/lecturas', [SeguimientoComunicadoController::class, 'index'])->whereNumber('comunicado');
            Route::get('{comunicado}/lecturas/resumen', [SeguimientoComunicadoController::class, 'resumen'])->whereNumber('comunicado');
            Route::get('{comunicado}/lecturas/excel', [SeguimientoComunicadoController::class, 'excel'])->whereNumber('comunicado');
        });
    });

    // Módulo 18 — Reportería e Inteligencia Institucional
    Route::prefix('reporteria')
        ->group(function () {
            Route::get('dashboard',
                [DashboardController::class, 'kpis'])
                ->middleware('role:admin-uath|auditor|director');

            // Reportes Ad Hoc y Configuración
            Route::get('configuraciones',
                [ReporteController::class, 'indexConfiguraciones'])
                ->middleware('role:admin-uath|auditor|director');
            Route::post('configuraciones',
                [ReporteController::class, 'storeConfiguracion'])
                ->middleware('role:admin-uath|auditor|director');
            Route::post('ad-hoc',
                [ReporteController::class, 'generarAdHoc'])
                ->middleware('role:admin-uath|auditor|director');

            // Reportes Asíncronos (Background)
            Route::post('background',
                [ReporteController::class, 'solicitarFondo'])
                ->middleware('role:admin-uath|auditor|director');
            Route::get('background/{job_id}',
                [ReporteController::class, 'estadoFondo'])
                ->middleware('role:admin-uath|auditor|director');
        });

});
