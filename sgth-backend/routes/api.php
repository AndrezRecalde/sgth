<?php

use App\Http\Controllers\Actividades\ActividadLaboralController;
use App\Http\Controllers\Admin\PermisoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Asistencia\ConsolidadoPermisoController;
use App\Http\Controllers\Asistencia\FolioPermisoController;
use App\Http\Controllers\Asistencia\MarcacionController;
use App\Http\Controllers\Asistencia\PeriodoVacacionController;
use App\Http\Controllers\Asistencia\PermisoServidorController;
use App\Http\Controllers\Asistencia\VacacionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Autoservicio\AutoservicioController;
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
use App\Http\Controllers\Disciplinario\VistoBuenoController;
use App\Http\Controllers\Dispensario\AdquisicionController;
use App\Http\Controllers\Dispensario\AgendaController;
use App\Http\Controllers\Dispensario\AlergiaPacienteController;
use App\Http\Controllers\Dispensario\AntecedentePacienteController;
use App\Http\Controllers\Dispensario\AtencionEnfermeriaController;
use App\Http\Controllers\Dispensario\CertificadoMedicoController;
use App\Http\Controllers\Dispensario\ConsultaMedicaController;
use App\Http\Controllers\Dispensario\DashboardDispensarioController;
use App\Http\Controllers\Dispensario\DiagnosticoCie10Controller;
use App\Http\Controllers\Dispensario\DisponibilidadController;
use App\Http\Controllers\Dispensario\FemoPdfController;
use App\Http\Controllers\Dispensario\FichaSaludOcupacionalController;
use App\Http\Controllers\Dispensario\HistoriaClinicaController;
use App\Http\Controllers\Dispensario\InventarioMedicinasController;
use App\Http\Controllers\Dispensario\ItemRecetaController;
use App\Http\Controllers\Dispensario\OdontogramaController;
use App\Http\Controllers\Dispensario\PacienteController;
use App\Http\Controllers\Dispensario\PersonalMedicoController;
use App\Http\Controllers\Dispensario\RecetaController;
use App\Http\Controllers\Dispensario\ResultadoMedicoController;
use App\Http\Controllers\Dispensario\SolicitudCertificacionController;
use App\Http\Controllers\Dispensario\TriajeController;
use App\Http\Controllers\Estructura\CargoController;
use App\Http\Controllers\Estructura\ExtensionTelefonicaController;
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
use App\Http\Controllers\Expediente\CertificadoLaboralController;
use App\Http\Controllers\Expediente\FirmanteAccionPersonalController;
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
use App\Http\Controllers\Expediente\MovimientoPersonalController;
use App\Http\Controllers\Expediente\VinculacionInicialController;
use App\Http\Controllers\Expediente\ServidorController;
use App\Http\Controllers\Expediente\SubrogacionController;
use App\Http\Controllers\Helpdesk\AreaDticController;
use App\Http\Controllers\Helpdesk\BaseConocimientoController;
use App\Http\Controllers\Helpdesk\ComentarioTicketController;
use App\Http\Controllers\Helpdesk\EncuestaSatisfaccionController;
use App\Http\Controllers\Helpdesk\SlaController;
use App\Http\Controllers\Helpdesk\TecnicoDticController;
use App\Http\Controllers\Helpdesk\TicketController;
use App\Http\Controllers\InventarioTi\AsignacionBienController;
use App\Http\Controllers\InventarioTi\AuditoriaInventarioController;
use App\Http\Controllers\InventarioTi\BajaController;
use App\Http\Controllers\InventarioTi\BienInformaticoController;
use App\Http\Controllers\InventarioTi\MantenimientoBienController;
use App\Http\Controllers\Nomina\ConceptoNominaController;
use App\Http\Controllers\Nomina\DescuentoRecurrenteController;
use App\Http\Controllers\Nomina\HandoffErpController;
use App\Http\Controllers\Nomina\NominaController;
use App\Http\Controllers\Nomina\RolPagoController;
use App\Http\Controllers\Reporte\ConfiguracionReporteMovimientoController;
use App\Http\Controllers\Reporte\ReporteSiithSutController;
use App\Http\Controllers\Reporteria\DashboardController;
use App\Http\Controllers\Reporteria\ReporteController;
use App\Http\Controllers\Seleccion\CalificacionController;
use App\Http\Controllers\Seleccion\ContenedorExpressController;
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
use App\Http\Controllers\Viatico\CatalogoViaticoController;
use App\Http\Controllers\Viatico\FacturaViaticoController;
use App\Http\Controllers\Viatico\InformeViaticoController;
use App\Http\Controllers\Viatico\LiquidacionViaticoController;
use App\Http\Controllers\Viatico\TramoViaticoController;
use App\Http\Controllers\Viatico\ViaticoController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');
    });

    // Endpoint público para escanear el QR del permiso físico
    Route::get('permisos/verificar/{folio}', [FolioPermisoController::class, 'verificar']);

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

    // Dispensario Médico: Búsqueda pública CIE-10 para autocompletado
    Route::get('dispensario/cie10/buscar', [DiagnosticoCie10Controller::class, 'buscar']);

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
    });

    // Rutas públicas de catálogo
    Route::get('catalogos/entidades-financieras', [EntidadFinancieraController::class, 'index']);
    Route::get('catalogos/tipos-unidad', [TipoUnidadController::class, 'index']);

    // Rutas de administración
    Route::apiResource('catalogos/entidades-financieras', EntidadFinancieraController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin-ti|admin-uath');

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

        // Catálogo de partidas presupuestarias. La lectura queda abierta a
        // cualquier usuario autenticado porque alimenta los selectores de
        // Puestos y de las acciones de personal; la gestión del catálogo la
        // restringe el middleware de rol, igual que las extensiones.
        Route::get('partidas-presupuestarias', [PartidaPresupuestariaController::class, 'index'])
            ->name('estructura.partidas-presupuestarias.index');
        Route::get('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'show'])
            ->name('estructura.partidas-presupuestarias.show');

        Route::middleware('role:admin-uath|admin-ti')->group(function () {
            Route::post('partidas-presupuestarias', [PartidaPresupuestariaController::class, 'store'])
                ->name('estructura.partidas-presupuestarias.store');
            Route::put('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'update'])
                ->name('estructura.partidas-presupuestarias.update');
            Route::delete('partidas-presupuestarias/{partida}', [PartidaPresupuestariaController::class, 'destroy'])
                ->name('estructura.partidas-presupuestarias.destroy');
        });

        // Directorio telefónico público para servidores
        Route::get('directorio-telefonico', [ExtensionTelefonicaController::class, 'index']);

        // Gestión de extensiones (protegida por middleware)
        Route::middleware('role:admin-ti|admin-uath')->group(function () {
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

    Route::post(
        'expediente/cargas-familiares/{cargaId}/discapacidades',
        [DiscapacidadCargaFamiliarController::class, 'store']
    )->name('carga.discapacidades.store');

    Route::delete(
        'expediente/cargas-familiares/{cargaId}/discapacidades/{id}',
        [DiscapacidadCargaFamiliarController::class, 'destroy']
    )->name('carga.discapacidades.destroy');

    Route::post(
        'expediente/cargas-familiares/{cargaId}/enfermedades',
        [EnfermedadCargaFamiliarController::class, 'store']
    )->name('carga.enfermedades.store');

    Route::delete(
        'expediente/cargas-familiares/{cargaId}/enfermedades/{id}',
        [EnfermedadCargaFamiliarController::class, 'destroy']
    )->name('carga.enfermedades.destroy');

    Route::prefix('expediente')->group(function () {
        Route::get('servidores-export/excel', [ExportServidoresController::class, 'excel']);
        Route::get('servidores-export/pdf', [ExportServidoresController::class, 'pdf']);
        // 'store' retirado: sin consumidor real, y su contrato de datos
        // (puesto/unidad/tipo_nombramiento requeridos al crear) contradice
        // la materialización tardía vía creaVinculo(). La creación real
        // pasa por 'basico' + un MovimientoPersonal de ingreso.
        Route::apiResource('servidores', ServidorController::class)->except(['store']);

        Route::prefix('servidores/{servidorId}')->group(function () {
            Route::get('documentos',
                [DocumentoServidorController::class, 'index'])
                ->name('documentos.index');
            Route::post('documentos',
                [DocumentoServidorController::class, 'store'])
                ->name('documentos.store');
            Route::delete('documentos/{documentoId}',
                [DocumentoServidorController::class, 'destroy'])
                ->name('documentos.destroy');
            Route::get('documentos/{documentoId}/descargar',
                [DocumentoServidorController::class, 'descargar'])
                ->name('documentos.descargar');
        });
        Route::get('servidores/{servidor}/movimientos', [MovimientoPersonalController::class, 'index']);
        Route::post('servidores/{servidorId}/movimientos', [MovimientoPersonalController::class, 'store'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::get('movimientos', [MovimientoPersonalController::class, 'bandeja'])
            ->middleware('role:admin-uath|asistente-uath');

        // Quién está temporalmente fuera (comisión de servicios, licencia sin
        // remuneración) y qué hueco falta cubrir con personal de apoyo.
        Route::get('ausencias-temporales', [AusenciaTemporalController::class, 'index'])
            ->middleware('role:admin-uath|asistente-uath');

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
        Route::get('movimientos/{movimiento}', [MovimientoPersonalController::class, 'show']);
        Route::put('movimientos/{movimiento}', [MovimientoPersonalController::class, 'update'])
            ->middleware('role:admin-uath');
        Route::put('movimientos/{movimiento}/transicionar', [MovimientoPersonalController::class, 'transicionar'])
            ->middleware('role:admin-uath|asistente-uath');
        Route::post('movimientos/{movimiento}/corregir', [MovimientoPersonalController::class, 'corregir'])
            ->middleware('role:admin-uath|asistente-uath');

        Route::get('servidores/{id}/certificado-laboral', [CertificadoLaboralController::class, 'generar']);
        Route::get('certificado-laboral/descargar/{archivo}', [CertificadoLaboralController::class, 'descargar'])
            ->name('expediente.certificado.descargar')
            ->middleware('signed');

        // Historiales gestionados por la UATH
        Route::prefix('servidores/{servidorId}')
            ->middleware('role:admin-uath|asistente-uath')
            ->group(function () {
                // 'store' queda fuera: un vínculo no se crea suelto, se crea
                // desde la acción de personal que lo respalda (regla de
                // Talento Humano). El alta directa sobrevive solo para carga
                // histórica, detrás de un rol aparte —ver más abajo—, porque
                // se salta la máquina de estados y no deja acción de personal.
                Route::get('actividad-laboral', [ContratoServidorController::class, 'actividadLaboral'])
                    ->name('contratos.actividadLaboral');

                Route::apiResource('contratos', ContratoServidorController::class)
                    ->parameters(['contratos' => 'contrato'])
                    ->only(['index', 'show']);

                Route::post('contratos', [ContratoServidorController::class, 'store'])
                    ->middleware('role:admin-ti')
                    ->name('contratos.store');
                Route::put('contratos/{contrato}/cerrar', [ContratoServidorController::class, 'cerrar'])
                    ->name('contratos.cerrar');
                // Único campo editable de un vínculo ya creado: el plazo
                // (prórroga o corrección de digitación), siempre con motivo.
                Route::put('contratos/{contrato}/plazo', [ContratoServidorController::class, 'reprogramarPlazo'])
                    ->name('contratos.plazo');
                Route::apiResource('discapacidades', DiscapacidadServidorController::class);
                Route::apiResource('enfermedades', EnfermedadCatastroficaServidorController::class);

                // Historial académico
                Route::get('historial-academico', [HistorialAcademicoController::class, 'index']);
                Route::post('historial-academico', [HistorialAcademicoController::class, 'store']);
                Route::put('historial-academico/{id}', [HistorialAcademicoController::class, 'update']);
                Route::delete('historial-academico/{id}', [HistorialAcademicoController::class, 'destroy']);

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

        // Cuentas bancarias
        Route::prefix('servidores/{id}/cuentas-bancarias')->group(function () {
            Route::get('/', [CuentaBancariaServidorController::class, 'index']);
            Route::post('/', [CuentaBancariaServidorController::class, 'store']);
            Route::put('{cuenta}', [CuentaBancariaServidorController::class, 'update']);
            Route::delete('{cuenta}', [CuentaBancariaServidorController::class, 'destroy']);
            Route::post('{cuenta}/set-principal', [CuentaBancariaServidorController::class, 'setPrincipal']);
        });

        Route::prefix('subrogaciones')->group(function () {
            Route::get('activas', [SubrogacionController::class, 'listarActivas']);
            Route::get('vigentes', [SubrogacionController::class, 'listarVigentes']);
            Route::get('servidor/{servidorId}', [SubrogacionController::class, 'listarPorServidor']);
            Route::post('/', [SubrogacionController::class, 'registrar']);
            Route::put('{id}/finalizar', [SubrogacionController::class, 'finalizar']);
            Route::put('{id}/cancelar', [SubrogacionController::class, 'cancelar']);
        });
    });

    // Módulo 03: Nómina y Remuneraciones
    Route::prefix('nomina')->group(function () {
        Route::get('/', [NominaController::class, 'index']);
        Route::post('/', [NominaController::class, 'calcular']);
        Route::get('{id}', [NominaController::class, 'show']);
        Route::post('{id}/cerrar', [NominaController::class, 'cerrar']);

        Route::get('{nominaId}/rol-pago/{servidorId}', [RolPagoController::class, 'show']);

        Route::get('conceptos', [ConceptoNominaController::class, 'index']);
        Route::get('handoffs', [HandoffErpController::class, 'index']);

        Route::apiResource('descuentos-recurrentes', DescuentoRecurrenteController::class)
            ->middleware('role:admin-uath|asistente-uath');
    });

    // Módulo 04: Asistencia, Permisos y Vacaciones
    Route::prefix('asistencia')->group(function () {
        // Biométrico (Solo lectura)
        Route::get('marcaciones', [MarcacionController::class, 'index']);

        Route::post(
            'marcaciones/online',
            [MarcacionController::class,
                'registrarOnline']
        )->name('asistencia.marcaciones.online');

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
            Route::get('{id}/exportar', [VacacionController::class, 'exportar'])
                ->name('asistencia.vacaciones.exportar');
            Route::put('{id}', [VacacionController::class, 'update']);
        });

        // Permisos
        Route::prefix('permisos')->group(function () {
            Route::get('/', [PermisoServidorController::class, 'index']);
            Route::post('/', [PermisoServidorController::class, 'store']);
            Route::get('{id}', [PermisoServidorController::class, 'show']);
            Route::get('{id}/exportar', [PermisoServidorController::class, 'exportar'])
                ->name('asistencia.permisos.exportar');
            Route::put('{id}/anular', [PermisoServidorController::class, 'anular'])
                ->middleware('role:admin-uath|asistente-uath');

            Route::post('confirmar/{folio}', [PermisoServidorController::class, 'confirmar'])
                ->middleware('role:recepcion|admin-uath|asistente-uath');
            Route::post('{id}/validar-ts', [PermisoServidorController::class, 'validar'])
                ->middleware('role:trabajo-social|admin-uath');
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
        });

    Route::prefix('asistencia/consolidado-permisos')
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
        Route::get('mis-vacaciones', [AutoservicioController::class, 'misVacaciones']);
        Route::get('mis-roles-pago', [AutoservicioController::class, 'misRolesPago']);
        Route::get('mi-expediente', [AutoservicioController::class, 'miExpediente']);
        Route::get('mis-actividades', [AutoservicioController::class, 'misActividades']);

        // Integración con Clínica y Módulo Asistencia
        Route::post('solicitar-cita', [AutoservicioController::class, 'solicitarCita']);
        Route::get('mi-historia-clinica', [AutoservicioController::class, 'miHistoriaClinica']);

        // Cargas familiares (autoservicio)
        Route::prefix('mis-cargas-familiares')->group(function () {
            Route::get('/', [CargaFamiliarController::class, 'misCargas']);
            Route::post('/', [CargaFamiliarController::class, 'storeMisCargas']);
            Route::put('{id}', [CargaFamiliarController::class, 'updateMisCargas']);
            Route::delete('{id}', [CargaFamiliarController::class, 'destroyMisCargas']);
        });
    });

    // Módulo 07: Selección e Incorporación
    Route::prefix('seleccion')
        ->middleware('role:admin-uath|analista-uath|admin-ti')
        ->group(function () {
            // Reclutamiento express: contenedores permanentes por modalidad.
            // Van antes que 'convocatorias/{id}' para que 'express' no se
            // interprete como un id.
            Route::get('express/resumen', [ContenedorExpressController::class, 'resumen']);
            Route::get('express/anios', [ContenedorExpressController::class, 'aniosDisponibles']);
            Route::get('express/{convocatoriaId}/aspirantes', [ContenedorExpressController::class, 'aspirantes']);

            // Convocatorias
            Route::get('convocatorias', [ConvocatoriaController::class, 'index']);
            Route::post('convocatorias', [ConvocatoriaController::class, 'store']);
            Route::get('convocatorias/{id}', [ConvocatoriaController::class, 'show']);
            Route::patch('convocatorias/{id}', [ConvocatoriaController::class, 'update']);
            Route::delete('convocatorias/{id}', [ConvocatoriaController::class, 'destroy']);
            Route::patch('convocatorias/{id}/publicar', [ConvocatoriaController::class, 'publicar']);

            // Postulantes por convocatoria
            Route::get('convocatorias/{convocatoriaId}/postulantes', [PostulanteController::class, 'index']);
            Route::post('convocatorias/{convocatoriaId}/postulantes', [PostulanteController::class, 'store']);
            Route::get('convocatorias/{convocatoriaId}/postulantes/{postulanteId}', [PostulanteController::class, 'show']);
            Route::patch('convocatorias/{convocatoriaId}/postulantes/{postulanteId}', [PostulanteController::class, 'update']);
            Route::delete('convocatorias/{convocatoriaId}/postulantes/{postulanteId}', [PostulanteController::class, 'destroy']);

            // Documentos del postulante
            Route::post('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/documentos', [PostulanteController::class, 'subirDocumento']);
            Route::delete('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/documentos/{documentoId}', [PostulanteController::class, 'eliminarDocumento']);

            // Criterios de evaluación
            Route::get('convocatorias/{convocatoriaId}/criterios', [CriterioEvaluacionController::class, 'index']);
            Route::post('convocatorias/{convocatoriaId}/criterios', [CriterioEvaluacionController::class, 'store']);
            Route::patch('convocatorias/{convocatoriaId}/criterios/{criterioId}', [CriterioEvaluacionController::class, 'update']);
            Route::delete('convocatorias/{convocatoriaId}/criterios/{criterioId}', [CriterioEvaluacionController::class, 'destroy']);

            // Plantillas de evaluación
            Route::get('plantillas', [PlantillaEvaluacionController::class, 'index']);
            Route::post('plantillas', [PlantillaEvaluacionController::class, 'store']);
            Route::get('plantillas/{id}', [PlantillaEvaluacionController::class, 'show']);
            Route::patch('plantillas/{id}', [PlantillaEvaluacionController::class, 'update']);
            Route::delete('plantillas/{id}', [PlantillaEvaluacionController::class, 'destroy']);
            Route::post('plantillas/{plantillaId}/criterios', [PlantillaEvaluacionController::class, 'agregarCriterio']);
            Route::delete('plantillas/{plantillaId}/criterios/{criterioId}', [PlantillaEvaluacionController::class, 'eliminarCriterio']);
            Route::post('plantillas/{plantillaId}/aplicar/{convocatoriaId}', [PlantillaEvaluacionController::class, 'aplicarAConvocatoria']);

            // Calificaciones por postulante
            Route::get('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/calificaciones', [CalificacionController::class, 'obtener']);
            Route::post('convocatorias/{convocatoriaId}/postulantes/{postulanteId}/calificaciones', [CalificacionController::class, 'guardar']);

            // Evaluación y selección
            Route::post('postulantes/{id}/calificar', [SeleccionController::class, 'calificar']);
            Route::post('convocatorias/{id}/declarar-ganador', [SeleccionController::class, 'declararGanador']);
            Route::post('convocatorias/{id}/confirmar-ganador', [SeleccionController::class, 'confirmarGanador']);
        });

    // Módulo 12 — Inventario de Bienes Informáticos
    Route::prefix('inventario')
        ->group(function () {
            // Bienes — DTIC gestiona, todos pueden ver el suyo
            Route::apiResource('bienes', BienInformaticoController::class)
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::get('bienes/{bien}/historial',
                [BienInformaticoController::class, 'historial'])
                ->middleware('role:tecnico-dtic|admin-ti|auditor');

            // Asignaciones
            Route::apiResource('asignaciones', AsignacionBienController::class)
                ->middleware('role:tecnico-dtic|admin-ti');

            // Mantenimientos
            Route::apiResource('mantenimientos', MantenimientoBienController::class)
                ->middleware('role:tecnico-dtic|admin-ti');

            // Auditoría física por QR
            Route::post('auditoria/escanear',
                [AuditoriaInventarioController::class, 'escanear'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('auditoria/registrar',
                [AuditoriaInventarioController::class, 'registrarAuditoria'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // Bajas conforme Contraloría
            Route::get('bajas',
                [BajaController::class, 'index'])
                ->middleware('role:tecnico-dtic|admin-ti|auditor');
            Route::post('bajas',
                [BajaController::class, 'store'])
                ->middleware('role:admin-ti');
        });

    // Módulo 13 — Helpdesk
    Route::prefix('helpdesk')
        ->group(function () {
            // Tickets — cualquier servidor autenticado puede crear
            Route::get('tickets',
                [TicketController::class, 'index']);
            Route::post('tickets',
                [TicketController::class, 'store']);
            Route::get('tickets/{ticket}',
                [TicketController::class, 'show']);
            Route::patch('tickets/{ticket}/estado',
                [TicketController::class, 'cambiarEstado'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/asignar',
                [TicketController::class, 'asignar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/escalar',
                [TicketController::class, 'escalar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/cerrar',
                [TicketController::class, 'cerrar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/vincular-bien',
                [TicketController::class, 'vincularBien'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // Comentarios
            Route::post('tickets/{ticket}/comentarios',
                [ComentarioTicketController::class, 'store']);
            Route::get('tickets/{ticket}/comentarios',
                [ComentarioTicketController::class, 'index']);

            // Áreas DTIC
            Route::apiResource('areas', AreaDticController::class)
                ->middleware('role:admin-ti');

            // Técnicos
            Route::apiResource('tecnicos', TecnicoDticController::class)
                ->middleware('role:admin-ti');
            Route::get('tecnicos/{tecnico}/carga-trabajo',
                [TecnicoDticController::class, 'cargaTrabajo'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // SLA
            Route::apiResource('slas', SlaController::class)
                ->middleware('role:admin-ti');

            // Base de conocimiento
            Route::apiResource('base-conocimiento',
                BaseConocimientoController::class);

            // Encuestas de satisfacción
            Route::get('encuestas-satisfaccion',
                [EncuestaSatisfaccionController::class, 'index'])
                ->middleware('role:admin-ti|auditor');
            Route::get('encuestas-satisfaccion/{encuesta}',
                [EncuestaSatisfaccionController::class, 'show'])
                ->middleware('role:admin-ti');
            Route::get('encuestas-satisfaccion/resultados',
                [EncuestaSatisfaccionController::class, 'resultados'])
                ->middleware('role:admin-ti|director|maxima-autoridad');
        });

    // Módulo 08: Evaluación del Desempeño
    Route::prefix('evaluacion')->group(function () {
        Route::post('evaluaciones/{evaluacionId}/servidor/{servidorId}', [EvaluacionController::class, 'registrarResultado']);
    });

    // Reporte de apoyo a transcripción manual SIITH/SUT (no es integración
    // con API externa: SIITH/SUT no la tienen, se transcribe a mano en su
    // portal — este módulo solo estructura los datos para minimizar error
    // de transcripción).
    Route::prefix('reportes/siith-sut')
        ->middleware('role:admin-uath|asistente-uath')
        ->group(function () {
            Route::get('movimientos', [ReporteSiithSutController::class, 'movimientos']);
            Route::get('mensual', [ReporteSiithSutController::class, 'mensual']);
            Route::get('configuracion', [ConfiguracionReporteMovimientoController::class, 'index']);
            Route::patch('configuracion/{configuracion}', [ConfiguracionReporteMovimientoController::class, 'update']);
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

        Route::prefix('vuelos')->group(function () {
            Route::get('/', [AutorizacionVueloController::class, 'index']);
            Route::post('{id}/aprobar', [AutorizacionVueloController::class, 'aprobar']);
            Route::post('{id}/rechazar', [AutorizacionVueloController::class, 'rechazar']);
            Route::post('{id}/documento', [AutorizacionVueloController::class, 'subirDocumento']);
        });

        Route::get('informe/descargar/{archivo}', [InformeViaticoController::class, 'descargar'])
            ->name('viaticos.informe.descargar')
            ->middleware('signed');

        Route::get('{id}', [ViaticoController::class, 'show']);
        Route::patch('{id}', [ViaticoController::class, 'update']);

        Route::post('servidor/{servidorId}/solicitar', [ViaticoController::class, 'solicitar'])
            ->name('viatico.solicitar.por.servidor');
        Route::post('{id}/solicitar', [ViaticoController::class, 'solicitar'])
            ->name('viatico.solicitar.propio');
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
                        'obtenerOCrear']);

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

    // Liquidaciones y Facturas
    Route::prefix('liquidaciones')->group(function () {
        Route::get('{id}/facturas', [FacturaViaticoController::class, 'index']);
        Route::post('{id}/facturas', [FacturaViaticoController::class, 'store']);
        Route::delete('{id}/facturas/{factura}', [FacturaViaticoController::class, 'destroy']);
    });

    // Módulo 14: Disciplinario — sumario administrativo (LOSEP) y visto bueno
    // (Código del Trabajo). Son procedimientos distintos según el régimen del
    // servidor, no dos nombres para lo mismo.
    Route::prefix('disciplinario')
        ->middleware('role:admin-uath')
        ->group(function () {
            Route::get('sumarios', [DisciplinarioController::class, 'index']);
            Route::post('sumarios', [DisciplinarioController::class, 'store']);
            Route::get('sumarios/{sumario}', [DisciplinarioController::class, 'show']);
            Route::put('sumarios/{sumario}/avanzar', [DisciplinarioController::class, 'avanzar']);
            Route::post('sumarios/{id}/resolver', [DisciplinarioController::class, 'resolver']);

            Route::get('vistos-buenos', [VistoBuenoController::class, 'index']);
            Route::post('vistos-buenos', [VistoBuenoController::class, 'store']);
            Route::get('vistos-buenos/{vistoBueno}', [VistoBuenoController::class, 'show']);
            Route::put('vistos-buenos/{vistoBueno}/transicionar', [VistoBuenoController::class, 'transicionar']);
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
                Route::put('horas-trabajadas/{id}', [HorasTrabajadasController::class, 'update']);
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

        // Agenda — accesible para todo el personal del dispensario
        Route::get('agenda/listos-para-consulta',
            [AgendaController::class, 'listosParaConsulta']
        )->name('agenda.listosParaConsulta');
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
        Route::get('agenda/por-folio/{folio}',
            [AgendaController::class, 'porFolio']
        )->name('agenda.porFolio');
        Route::apiResource('agenda', AgendaController::class)
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario');

        Route::get('triaje/pendientes',
            [TriajeController::class, 'pendientes']
        )->name('dispensario.triaje.pendientes');

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

        // Dashboard Estadístico — SOLO admin-dispensario (y máxima autoridad)
        Route::get('dashboard/kpis', [DashboardDispensarioController::class, 'kpis'])
            ->middleware('role:admin-dispensario|maxima-autoridad');

        Route::get('personal-medico',
            [PersonalMedicoController::class, 'index']
        )->name('dispensario.personal-medico');

        Route::get('disponibilidad/mi-estado',
            [DisponibilidadController::class, 'miEstado']
        )->name('dispensario.disponibilidad.miEstado');

        Route::post('disponibilidad/alternar',
            [DisponibilidadController::class, 'alternar']
        )->name('dispensario.disponibilidad.alternar');

        Route::get('pacientes/buscar',
            [PacienteController::class, 'buscar']
        )->name('dispensario.pacientes.buscar');

        Route::get('atenciones-enfermeria',
            [AtencionEnfermeriaController::class, 'index']
        )->name('dispensario.atenciones-enfermeria.index');

        Route::post('atenciones-enfermeria',
            [AtencionEnfermeriaController::class, 'store']
        )->name('dispensario.atenciones-enfermeria.store');

        Route::patch('atenciones-enfermeria/{id}/anular',
            [AtencionEnfermeriaController::class, 'anular']
        )->name('dispensario.atenciones-enfermeria.anular');

        Route::get('catalogo-servicios-enfermeria',
            [AtencionEnfermeriaController::class, 'catalogo']
        )->name('dispensario.catalogo-servicios-enfermeria');

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

        // Consultas médicas — SOLO médicos y odontólogos crean,
        // todo el personal médico puede ver
        Route::prefix('consultas')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('/', [ConsultaMedicaController::class, 'index']);
                Route::post('/', [ConsultaMedicaController::class, 'store']);
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
                Route::post('procedimientos', [OdontogramaController::class, 'registrarProcedimiento']);
                Route::patch('procedimientos/{id}/anular', [OdontogramaController::class, 'anularProcedimiento']);
                Route::get('piezas/{piezaId}/historial', [OdontogramaController::class, 'historialPieza']);
            });

        Route::get('certificados-medicos',
            [CertificadoMedicoController::class, 'index']
        )->name('dispensario.certificados.index');

        Route::post('certificados-medicos',
            [CertificadoMedicoController::class, 'store']
        )->name('dispensario.certificados.store');

        Route::get('certificados-medicos/{id}',
            [CertificadoMedicoController::class, 'show']
        )->name('dispensario.certificados.show');

        // Recetas — médicos emiten, enfermeras y admin despachan
        Route::prefix('recetas')->group(function () {
            Route::get('/', [RecetaController::class, 'index'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::post('/', [RecetaController::class, 'store'])
                ->middleware('role:medico|odontologo');
            Route::get('{id}', [RecetaController::class, 'show'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::post('{id}/despachar', [RecetaController::class, 'despachar'])
                ->middleware('role:enfermera|admin-dispensario');
            Route::post('{id}/anular', [RecetaController::class, 'anular'])
                ->middleware('role:medico|odontologo|admin-dispensario');
            Route::patch('{recetaId}/items/{itemId}',
                [ItemRecetaController::class, 'update']
            )->middleware('role:medico|odontologo');
            Route::delete('{recetaId}/items/{itemId}',
                [ItemRecetaController::class, 'destroy']
            )->middleware('role:medico|odontologo');
        });

        // Inventario — todo el personal del dispensario
        Route::prefix('inventario')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('medicinas/buscar',
                    [InventarioMedicinasController::class, 'buscar']);
                Route::get('medicinas/stock-bajo',
                    [InventarioMedicinasController::class, 'contarStockBajo']);
                Route::apiResource('medicinas', InventarioMedicinasController::class);
                Route::get('medicinas/{id}/kardex',
                    [InventarioMedicinasController::class, 'kardex']);
                Route::post('medicinas/{medicina}/baja',
                    [InventarioMedicinasController::class, 'registrarBaja']);
                Route::post('medicinas/{medicina}/ajustar-inventario',
                    [InventarioMedicinasController::class, 'ajustarInventario']);
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
            ->middleware('role:admin-uath|analista-uath|admin-dispensario')
            ->group(function () {
                Route::post('lote', [SolicitudCertificacionController::class, 'storeLote']);
            });

        // Solicitudes de certificación médica
        Route::prefix('solicitudes-certificacion')
            ->middleware('role:medico|admin-dispensario|admin-uath|analista-uath')
            ->group(function () {
                Route::get('/', [SolicitudCertificacionController::class, 'index']);
                Route::get('{id}', [SolicitudCertificacionController::class, 'show']);
                Route::patch('{id}/iniciar', [SolicitudCertificacionController::class, 'iniciarProceso']);
                Route::patch('{id}/completar', [SolicitudCertificacionController::class, 'completar']);
                Route::post('{id}/confirmar-incorporacion', [SolicitudCertificacionController::class, 'confirmarIncorporacion']);
            });

        // Fichas de salud ocupacional
        Route::prefix('fichas-sso')
            ->middleware('role:medico|admin-dispensario')
            ->group(function () {
                // Catálogo oficial de factores de riesgo del MSP (sección G).
                // Va antes de '{id}' para que 'catalogo-riesgos' no se lea como id.
                Route::get('catalogo-riesgos', [FichaSaludOcupacionalController::class, 'catalogoRiesgos']);
                Route::get('/', [FichaSaludOcupacionalController::class, 'index']);
                Route::post('/', [FichaSaludOcupacionalController::class, 'store']);
                Route::get('{id}', [FichaSaludOcupacionalController::class, 'show']);
                Route::patch('{id}', [FichaSaludOcupacionalController::class, 'update']);
                Route::get('{id}/pdf', [FemoPdfController::class, 'generar']);
            });

        // Resultados médicos
        Route::prefix('resultados-medicos')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('/', [ResultadoMedicoController::class, 'index']);
                Route::post('/', [ResultadoMedicoController::class, 'store']);
                Route::delete('{id}', [ResultadoMedicoController::class, 'destroy'])
                    ->middleware('role:medico|odontologo|admin-dispensario');
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

    // Módulo 16 — Actividades Laborales
    Route::prefix('actividades')
        ->group(function () {
            Route::apiResource('/', ActividadLaboralController::class)
                ->parameters(['' => 'actividad']); // Para que las rutas resource funcionen con /
            Route::get('por-unidad',
                [ActividadLaboralController::class, 'porUnidad'])
                ->middleware('role:jefe-unidad|director|admin-uath');
            Route::post('exportar-informe',
                [ActividadLaboralController::class, 'exportarInforme']);
        });

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
                ->middleware('role:admin-uath|director|maxima-autoridad');
        });

    // Módulo 18 — Reportería e Inteligencia Institucional
    Route::prefix('reporteria')
        ->group(function () {
            Route::get('dashboard',
                [DashboardController::class, 'kpis'])
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');

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
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');
            Route::get('background/{job_id}',
                [ReporteController::class, 'estadoFondo'])
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');
        });

});
