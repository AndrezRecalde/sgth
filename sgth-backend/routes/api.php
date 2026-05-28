<?php

use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login'])
            ->middleware('throttle:5,1');
    });

    // Endpoint público para escanear el QR del permiso físico
    Route::get('permisos/verificar/{folio}', [\App\Http\Controllers\Asistencia\FolioPermisoController::class, 'verificar']);

    // Endpoint público protegido criptográficamente mediante firmas temporales (URL firmada)
    Route::get('sgd/documentos/{documento}/descargar', [\App\Http\Controllers\Sgd\DocumentoInstitucionalController::class, 'descargar'])
        ->name('sgd.documentos.descargar');
        
    // Catálogos geográficos públicos
    Route::get('catalogos/provincias', [\App\Http\Controllers\Catalogo\ProvinciaController::class, 'index']);
    Route::get('catalogos/provincias/{id}/cantones', [\App\Http\Controllers\Catalogo\CantonController::class, 'porProvincia']);

    // Dispensario Médico: Búsqueda pública CIE-10 para autocompletado
    Route::get('dispensario/cie10/buscar', [\App\Http\Controllers\Dispensario\DiagnosticoCie10Controller::class, 'buscar']);
});

// ── Rutas autenticadas ─────────────────────────────────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'primer-login'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);
        Route::get('perfil', [\App\Http\Controllers\Auth\AuthController::class, 'perfil']);
        
        // Esta ruta debe ignorar la restricción del primer-login (manejado internamente por el middleware)
        Route::post('cambiar-contrasena',
            [\App\Http\Controllers\Auth\AuthController::class, 'cambiarContrasenaInicial']
        );
    });

    // Rutas públicas de catálogo
    Route::get('catalogos/entidades-financieras', [\App\Http\Controllers\Catalogo\EntidadFinancieraController::class, 'index']);
    Route::get('catalogos/tipos-unidad', [\App\Http\Controllers\Catalogo\TipoUnidadController::class, 'index']);

    // Rutas de administración
    Route::apiResource('catalogos/entidades-financieras', \App\Http\Controllers\Catalogo\EntidadFinancieraController::class)
        ->except(['index', 'show'])
        ->middleware('role:admin-ti|admin-uath');

    // Módulo 01: Estructura Organizacional
    Route::prefix('estructura')->group(function () {
        Route::get('organigrama', \App\Http\Controllers\Estructura\OrganigramaController::class);
        Route::apiResource('unidades-administrativas', \App\Http\Controllers\Estructura\UnidadAdministrativaController::class);
        Route::apiResource('puestos', \App\Http\Controllers\Estructura\PuestoController::class);

        Route::get('cargos', [\App\Http\Controllers\Estructura\CargoController::class, 'index'])
            ->name('estructura.cargos.index');
        Route::post('cargos', [\App\Http\Controllers\Estructura\CargoController::class, 'store'])
            ->name('estructura.cargos.store');
        Route::put('cargos/{id}', [\App\Http\Controllers\Estructura\CargoController::class, 'update'])
            ->name('estructura.cargos.update');
        Route::delete('cargos/{id}', [\App\Http\Controllers\Estructura\CargoController::class, 'destroy'])
            ->name('estructura.cargos.destroy');
        
        Route::get('grupos-ocupacionales', [\App\Http\Controllers\Estructura\GrupoOcupacionalController::class, 'index'])
            ->name('estructura.grupos-ocupacionales');
        
        // Directorio telefónico público para servidores
        Route::get('directorio-telefonico', [\App\Http\Controllers\Estructura\ExtensionTelefonicaController::class, 'index']);
        
        // Gestión de extensiones (protegida por middleware)
        Route::middleware('role:admin-ti|admin-uath')->group(function () {
            Route::post('extensiones', [\App\Http\Controllers\Estructura\ExtensionTelefonicaController::class, 'store']);
            Route::put('extensiones/{id}', [\App\Http\Controllers\Estructura\ExtensionTelefonicaController::class, 'update']);
            Route::delete('extensiones/{id}', [\App\Http\Controllers\Estructura\ExtensionTelefonicaController::class, 'destroy']);
        });
    });

    // Módulo 02: Expediente Digital
    Route::get('expediente/servidores/sin-usuario',
        [\App\Http\Controllers\Expediente\ServidorController::class, 'sinUsuario'])
        ->name('servidores.sinUsuario');

    Route::post('expediente/servidores/basico',
        [\App\Http\Controllers\Expediente\ServidorController::class, 'storeBasico'])
        ->name('servidores.storeBasico');

    Route::post(
        'expediente/cargas-familiares/{cargaId}/discapacidades',
        [\App\Http\Controllers\Expediente\DiscapacidadCargaFamiliarController::class, 'store']
    )->name('carga.discapacidades.store');

    Route::delete(
        'expediente/cargas-familiares/{cargaId}/discapacidades/{id}',
        [\App\Http\Controllers\Expediente\DiscapacidadCargaFamiliarController::class, 'destroy']
    )->name('carga.discapacidades.destroy');

    Route::post(
        'expediente/cargas-familiares/{cargaId}/enfermedades',
        [\App\Http\Controllers\Expediente\EnfermedadCargaFamiliarController::class, 'store']
    )->name('carga.enfermedades.store');

    Route::delete(
        'expediente/cargas-familiares/{cargaId}/enfermedades/{id}',
        [\App\Http\Controllers\Expediente\EnfermedadCargaFamiliarController::class, 'destroy']
    )->name('carga.enfermedades.destroy');

    Route::prefix('expediente')->group(function () {
        Route::apiResource('servidores', \App\Http\Controllers\Expediente\ServidorController::class);
        
        Route::prefix('servidores/{servidorId}')->group(function () {
            Route::get('documentos',
                [\App\Http\Controllers\Expediente\DocumentoServidorController::class, 'index'])
                ->name('documentos.index');
            Route::post('documentos',
                [\App\Http\Controllers\Expediente\DocumentoServidorController::class, 'store'])
                ->name('documentos.store');
            Route::delete('documentos/{documentoId}',
                [\App\Http\Controllers\Expediente\DocumentoServidorController::class, 'destroy'])
                ->name('documentos.destroy');
            Route::get('documentos/{documentoId}/descargar',
                [\App\Http\Controllers\Expediente\DocumentoServidorController::class, 'descargar'])
                ->name('documentos.descargar');
        });
        Route::get('servidores/{servidor}/movimientos', [\App\Http\Controllers\Expediente\MovimientoPersonalController::class, 'index']);

        Route::get('servidores/{id}/certificado-laboral', [\App\Http\Controllers\Expediente\CertificadoLaboralController::class, 'generar']);
        Route::get('certificado-laboral/descargar/{archivo}', [\App\Http\Controllers\Expediente\CertificadoLaboralController::class, 'descargar'])
            ->name('expediente.certificado.descargar')
            ->middleware('signed');

        // Historiales gestionados por la UATH
        Route::prefix('servidores/{servidorId}')
            ->middleware('role:admin-uath|asistente-uath')
            ->group(function () {
                Route::apiResource('contratos', \App\Http\Controllers\Expediente\ContratoServidorController::class)->parameters(['contratos' => 'contrato']);
                Route::apiResource('discapacidades', \App\Http\Controllers\Expediente\DiscapacidadServidorController::class);
                Route::apiResource('enfermedades', \App\Http\Controllers\Expediente\EnfermedadCatastroficaServidorController::class);

                // Historial académico
                Route::get('historial-academico', [\App\Http\Controllers\Expediente\HistorialAcademicoController::class, 'index']);
                Route::post('historial-academico', [\App\Http\Controllers\Expediente\HistorialAcademicoController::class, 'store']);
                Route::put('historial-academico/{id}', [\App\Http\Controllers\Expediente\HistorialAcademicoController::class, 'update']);
                Route::delete('historial-academico/{id}', [\App\Http\Controllers\Expediente\HistorialAcademicoController::class, 'destroy']);

                // Cargas familiares
                Route::get('cargas-familiares', [\App\Http\Controllers\Expediente\CargaFamiliarController::class, 'index']);
                Route::post('cargas-familiares', [\App\Http\Controllers\Expediente\CargaFamiliarController::class, 'store']);
                Route::put('cargas-familiares/{id}', [\App\Http\Controllers\Expediente\CargaFamiliarController::class, 'update']);
                Route::delete('cargas-familiares/{id}', [\App\Http\Controllers\Expediente\CargaFamiliarController::class, 'destroy']);

                // Declaraciones juramentadas
                Route::get('declaraciones-juramentadas', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'index']);
                Route::post('declaraciones-juramentadas', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'store']);
                Route::delete('declaraciones-juramentadas/{id}', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'destroy']);
                Route::get('declaraciones-juramentadas/exportar', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'exportar']);
                Route::get('declaraciones-juramentadas/{id}/documento', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'verDocumento']);
                Route::put('declaraciones-juramentadas/{id}', [\App\Http\Controllers\Expediente\DeclaracionJuramentadaController::class, 'update'])->name('declaraciones.update');

                // Beneficiarios (Dispensario) gestionados por UATH
                Route::prefix('beneficiarios')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'indexUath']);
                    Route::post('/', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'storeUath']);
                    Route::put('{id}', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'updateUath']);
                    Route::delete('{id}', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'destroyUath']);
                });
            });

        // Cuentas bancarias
        Route::prefix('servidores/{id}/cuentas-bancarias')->group(function () {
            Route::get('/', [\App\Http\Controllers\Expediente\CuentaBancariaServidorController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Expediente\CuentaBancariaServidorController::class, 'store']);
            Route::put('{cuenta}', [\App\Http\Controllers\Expediente\CuentaBancariaServidorController::class, 'update']);
            Route::delete('{cuenta}', [\App\Http\Controllers\Expediente\CuentaBancariaServidorController::class, 'destroy']);
            Route::post('{cuenta}/set-principal', [\App\Http\Controllers\Expediente\CuentaBancariaServidorController::class, 'setPrincipal']);
        });

        Route::prefix('subrogaciones')->group(function () {
            Route::get('activas', [\App\Http\Controllers\Expediente\SubrogacionController::class, 'listarActivas']);
            Route::get('servidor/{servidorId}', [\App\Http\Controllers\Expediente\SubrogacionController::class, 'listarPorServidor']);
            Route::post('/', [\App\Http\Controllers\Expediente\SubrogacionController::class, 'registrar']);
            Route::put('{id}/finalizar', [\App\Http\Controllers\Expediente\SubrogacionController::class, 'finalizar']);
            Route::put('{id}/cancelar', [\App\Http\Controllers\Expediente\SubrogacionController::class, 'cancelar']);
        });
    });

    // Módulo 03: Nómina y Remuneraciones
    Route::prefix('nomina')->group(function () {
        Route::get('/', [\App\Http\Controllers\Nomina\NominaController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Nomina\NominaController::class, 'calcular']);
        Route::get('{id}', [\App\Http\Controllers\Nomina\NominaController::class, 'show']);
        Route::post('{id}/cerrar', [\App\Http\Controllers\Nomina\NominaController::class, 'cerrar']);
        
        Route::get('{nominaId}/rol-pago/{servidorId}', [\App\Http\Controllers\Nomina\RolPagoController::class, 'show']);
        
        Route::get('conceptos', [\App\Http\Controllers\Nomina\ConceptoNominaController::class, 'index']);
        Route::get('handoffs', [\App\Http\Controllers\Nomina\HandoffErpController::class, 'index']);
        
        Route::apiResource('descuentos-recurrentes', \App\Http\Controllers\Nomina\DescuentoRecurrenteController::class)
            ->middleware('role:admin-uath|asistente-uath');
    });

    // Módulo 04: Asistencia, Permisos y Vacaciones
    Route::prefix('asistencia')->group(function () {
        // Biométrico (Solo lectura)
        Route::get('marcaciones', [\App\Http\Controllers\Asistencia\MarcacionController::class, 'index']);

        // Vacaciones
        Route::prefix('vacaciones')->group(function () {
            Route::get('/', [\App\Http\Controllers\Asistencia\VacacionController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Asistencia\VacacionController::class, 'store']);
            Route::get('saldo/{servidorId}', [\App\Http\Controllers\Asistencia\VacacionController::class, 'saldo']);
            Route::put('{id}', [\App\Http\Controllers\Asistencia\VacacionController::class, 'update']);
        });

        // Permisos
        Route::prefix('permisos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'store']);
            Route::get('{id}', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'show']);
            Route::put('{id}/anular', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'anular'])
                ->middleware('role:admin-uath|asistente-uath');
            
            Route::post('confirmar/{folio}', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'confirmar'])
                ->middleware('role:recepcion');
            Route::post('{id}/validar-ts', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'validar'])
                ->middleware('role:trabajo-social');
        });
    });

    // Módulo 05: Sistema de Gestión Documental (SGD)
    Route::prefix('sgd')->group(function () {
        Route::post('documentos', [\App\Http\Controllers\Sgd\DocumentoInstitucionalController::class, 'store']);
        Route::get('documentos/{id}/generar-enlace', [\App\Http\Controllers\Sgd\DocumentoInstitucionalController::class, 'generarEnlace']);
    });

    // Módulo 06: Autoservicio
    Route::prefix('autoservicio')->group(function () {
        Route::get('mis-permisos', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'misPermisos']);
        Route::get('mis-vacaciones', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'misVacaciones']);
        Route::get('mis-roles-pago', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'misRolesPago']);
        Route::get('mi-expediente', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'miExpediente']);
        Route::get('mis-actividades', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'misActividades']);
        
        // Integración con Clínica y Módulo Asistencia
        Route::post('solicitar-cita', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'solicitarCita']);
        Route::get('mi-historia-clinica', [\App\Http\Controllers\Autoservicio\AutoservicioController::class, 'miHistoriaClinica']);

        // Beneficiarios Familiares
        Route::prefix('mis-beneficiarios')->group(function () {
            Route::get('/', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'misBeneficiarios']);
            Route::post('/', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'storeMisBeneficiarios']);
            Route::put('{id}', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'updateMisBeneficiarios']);
            Route::delete('{id}', [\App\Http\Controllers\Dispensario\BeneficiarioController::class, 'destroyMisBeneficiarios']);
        });
    });

    // Módulo 07: Selección e Incorporación
    Route::prefix('seleccion')->group(function () {
        Route::post('postulantes/{id}/calificar', [\App\Http\Controllers\Seleccion\SeleccionController::class, 'calificar']);
        Route::post('convocatorias/{id}/declarar-ganador', [\App\Http\Controllers\Seleccion\SeleccionController::class, 'declararGanador']);
    });

    // Módulo 12 — Inventario de Bienes Informáticos
    Route::prefix('inventario')
        ->group(function () {
            // Bienes — DTIC gestiona, todos pueden ver el suyo
            Route::apiResource('bienes', \App\Http\Controllers\InventarioTi\BienInformaticoController::class)
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::get('bienes/{bien}/historial',
                [\App\Http\Controllers\InventarioTi\BienInformaticoController::class, 'historial'])
                ->middleware('role:tecnico-dtic|admin-ti|auditor');

            // Asignaciones
            Route::apiResource('asignaciones', \App\Http\Controllers\InventarioTi\AsignacionBienController::class)
                ->middleware('role:tecnico-dtic|admin-ti');

            // Mantenimientos
            Route::apiResource('mantenimientos', \App\Http\Controllers\InventarioTi\MantenimientoBienController::class)
                ->middleware('role:tecnico-dtic|admin-ti');

            // Auditoría física por QR
            Route::post('auditoria/escanear',
                [\App\Http\Controllers\InventarioTi\AuditoriaInventarioController::class, 'escanear'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('auditoria/registrar',
                [\App\Http\Controllers\InventarioTi\AuditoriaInventarioController::class, 'registrarAuditoria'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // Bajas conforme Contraloría
            Route::get('bajas',
                [\App\Http\Controllers\InventarioTi\BajaController::class, 'index'])
                ->middleware('role:tecnico-dtic|admin-ti|auditor');
            Route::post('bajas',
                [\App\Http\Controllers\InventarioTi\BajaController::class, 'store'])
                ->middleware('role:admin-ti');
        });

    // Módulo 13 — Helpdesk
    Route::prefix('helpdesk')
        ->group(function () {
            // Tickets — cualquier servidor autenticado puede crear
            Route::get('tickets',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'index']);
            Route::post('tickets',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'store']);
            Route::get('tickets/{ticket}',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'show']);
            Route::patch('tickets/{ticket}/estado',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'cambiarEstado'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/asignar',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'asignar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/escalar',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'escalar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/cerrar',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'cerrar'])
                ->middleware('role:tecnico-dtic|admin-ti');
            Route::post('tickets/{ticket}/vincular-bien',
                [\App\Http\Controllers\Helpdesk\TicketController::class, 'vincularBien'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // Comentarios
            Route::post('tickets/{ticket}/comentarios',
                [\App\Http\Controllers\Helpdesk\ComentarioTicketController::class, 'store']);
            Route::get('tickets/{ticket}/comentarios',
                [\App\Http\Controllers\Helpdesk\ComentarioTicketController::class, 'index']);

            // Áreas DTIC
            Route::apiResource('areas', \App\Http\Controllers\Helpdesk\AreaDticController::class)
                ->middleware('role:admin-ti');

            // Técnicos
            Route::apiResource('tecnicos', \App\Http\Controllers\Helpdesk\TecnicoDticController::class)
                ->middleware('role:admin-ti');
            Route::get('tecnicos/{tecnico}/carga-trabajo',
                [\App\Http\Controllers\Helpdesk\TecnicoDticController::class, 'cargaTrabajo'])
                ->middleware('role:tecnico-dtic|admin-ti');

            // SLA
            Route::apiResource('slas', \App\Http\Controllers\Helpdesk\SlaController::class)
                ->middleware('role:admin-ti');

            // Base de conocimiento
            Route::apiResource('base-conocimiento',
                \App\Http\Controllers\Helpdesk\BaseConocimientoController::class);

            // Encuestas de satisfacción
            Route::get('encuestas-satisfaccion',
                [\App\Http\Controllers\Helpdesk\EncuestaSatisfaccionController::class, 'index'])
                ->middleware('role:admin-ti|auditor');
            Route::get('encuestas-satisfaccion/{encuesta}',
                [\App\Http\Controllers\Helpdesk\EncuestaSatisfaccionController::class, 'show'])
                ->middleware('role:admin-ti');
            Route::get('encuestas-satisfaccion/resultados',
                [\App\Http\Controllers\Helpdesk\EncuestaSatisfaccionController::class, 'resultados'])
                ->middleware('role:admin-ti|director|maxima-autoridad');
        });

    // Módulo 08: Evaluación del Desempeño
    Route::prefix('evaluacion')->group(function () {
        Route::post('evaluaciones/{evaluacionId}/servidor/{servidorId}', [\App\Http\Controllers\Evaluacion\EvaluacionController::class, 'registrarResultado']);
    });

    // Módulo 09: Viáticos
    Route::prefix('viaticos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Viatico\ViaticoController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Viatico\ViaticoController::class, 'store']);
        Route::get('{id}', [\App\Http\Controllers\Viatico\ViaticoController::class, 'show']);
        
        Route::post('servidor/{servidorId}/solicitar', [\App\Http\Controllers\Viatico\ViaticoController::class, 'solicitar'])
            ->name('viatico.solicitar.por.servidor');
        Route::post('{id}/solicitar', [\App\Http\Controllers\Viatico\ViaticoController::class, 'solicitar'])
            ->name('viatico.solicitar.propio');
        Route::post('{viaticoId}/liquidar', [\App\Http\Controllers\Viatico\ViaticoController::class, 'liquidar']);
        
        Route::get('{id}/informe/generar-enlace', [\App\Http\Controllers\Viatico\InformeViaticoController::class, 'generarEnlace']);
        Route::get('informe/descargar/{archivo}', [\App\Http\Controllers\Viatico\InformeViaticoController::class, 'descargar'])
            ->name('viaticos.informe.descargar')
            ->middleware('signed');
            
        // Destinos
        Route::prefix('{id}/destinos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viatico\DestinoViaticoController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Viatico\DestinoViaticoController::class, 'store']);
            Route::put('{destino}', [\App\Http\Controllers\Viatico\DestinoViaticoController::class, 'update']);
            Route::delete('{destino}', [\App\Http\Controllers\Viatico\DestinoViaticoController::class, 'destroy']);
        });

        // Transportes
        Route::prefix('{id}/transportes')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viatico\TransporteViaticoController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Viatico\TransporteViaticoController::class, 'store']);
            Route::put('{transporte}', [\App\Http\Controllers\Viatico\TransporteViaticoController::class, 'update']);
            Route::delete('{transporte}', [\App\Http\Controllers\Viatico\TransporteViaticoController::class, 'destroy']);
        });
        
        Route::prefix('vuelos')->group(function () {
            Route::get('/', [\App\Http\Controllers\Viatico\AutorizacionVueloController::class, 'index'])
                ->middleware('role:maxima-autoridad');
            Route::post('{id}/aprobar', [\App\Http\Controllers\Viatico\AutorizacionVueloController::class, 'aprobar'])
                ->middleware('role:maxima-autoridad');
            Route::post('{id}/rechazar', [\App\Http\Controllers\Viatico\AutorizacionVueloController::class, 'rechazar'])
                ->middleware('role:maxima-autoridad');
            Route::post('{id}/documento', [\App\Http\Controllers\Viatico\AutorizacionVueloController::class, 'subirDocumento']);
        });
    });

    // Liquidaciones y Facturas
    Route::prefix('liquidaciones')->group(function () {
        Route::get('{id}/facturas', [\App\Http\Controllers\Viatico\FacturaViaticoController::class, 'index']);
        Route::post('{id}/facturas', [\App\Http\Controllers\Viatico\FacturaViaticoController::class, 'store']);
        Route::delete('{id}/facturas/{factura}', [\App\Http\Controllers\Viatico\FacturaViaticoController::class, 'destroy']);
    });

    // Comisiones
    Route::prefix('comisiones')->group(function () {
        Route::get('/', [\App\Http\Controllers\Viatico\ComisionController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Viatico\ComisionController::class, 'store']);
        Route::get('{id}', [\App\Http\Controllers\Viatico\ComisionController::class, 'show']);
    });

    // Módulo 14: Disciplinario
    Route::prefix('disciplinario')->group(function () {
        Route::post('sumarios/{id}/resolver', [\App\Http\Controllers\Disciplinario\DisciplinarioController::class, 'resolver'])
            ->middleware('role:admin-uath');
    });

    // Módulo 10 — SSO
    Route::prefix('sso')
        ->middleware('role:admin-uath|asistente-uath|auditor')
        ->group(function () {
            Route::apiResource('riesgos', \App\Http\Controllers\Sso\RiesgoLaboralController::class);
            Route::apiResource('accidentes', \App\Http\Controllers\Sso\AccidenteTrabajoController::class);
            Route::apiResource('equipos-proteccion', \App\Http\Controllers\Sso\EquipoProteccionController::class);
        });

    // Módulo 11 — Dispensario Médico
    Route::prefix('dispensario')->group(function () {

        // Agenda — accesible para todo el personal del dispensario
        Route::apiResource('agenda', \App\Http\Controllers\Dispensario\AgendaController::class)
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario');

        // Triaje
        Route::prefix('agenda/{agendaId}/triaje')->group(function () {
            Route::post('/', [\App\Http\Controllers\Dispensario\TriajeController::class, 'store'])
                ->middleware('role:enfermera|admin-dispensario');
            Route::get('/', [\App\Http\Controllers\Dispensario\TriajeController::class, 'show'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
        });

        // Dashboard Estadístico — SOLO admin-dispensario (y máxima autoridad)
        Route::get('dashboard/kpis', [\App\Http\Controllers\Dispensario\DashboardDispensarioController::class, 'kpis'])
            ->middleware('role:admin-dispensario|maxima-autoridad');

        // Historias clínicas — SOLO personal médico
        Route::prefix('historias-clinicas')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'show']);

                // Alergias
                Route::prefix('{id}/alergias')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Dispensario\AlergiaPacienteController::class, 'index']);
                    Route::post('/', [\App\Http\Controllers\Dispensario\AlergiaPacienteController::class, 'store']);
                    Route::delete('{alergia}', [\App\Http\Controllers\Dispensario\AlergiaPacienteController::class, 'destroy']);
                });

                // Antecedentes
                Route::prefix('{id}/antecedentes')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Dispensario\AntecedentePacienteController::class, 'index']);
                    Route::post('/', [\App\Http\Controllers\Dispensario\AntecedentePacienteController::class, 'store']);
                    Route::delete('{antecedente}', [\App\Http\Controllers\Dispensario\AntecedentePacienteController::class, 'destroy']);
                });
            });

        // Consultas médicas — SOLO médicos y odontólogos crean,
        // todo el personal médico puede ver
        Route::prefix('consultas')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'show']);
            });

        // Recetas — médicos emiten, enfermeras y admin despachan
        Route::prefix('recetas')->group(function () {
            Route::post('/', [\App\Http\Controllers\Dispensario\RecetaController::class, 'store'])
                ->middleware('role:medico|odontologo');
            Route::get('{id}', [\App\Http\Controllers\Dispensario\RecetaController::class, 'show'])
                ->middleware('role:medico|odontologo|enfermera|admin-dispensario');
            Route::post('{id}/despachar', [\App\Http\Controllers\Dispensario\RecetaController::class, 'despachar'])
                ->middleware('role:enfermera|admin-dispensario');
        });

        // Inventario — todo el personal del dispensario
        Route::prefix('inventario')
            ->middleware('role:medico|odontologo|enfermera|admin-dispensario')
            ->group(function () {
                Route::apiResource('medicinas', \App\Http\Controllers\Dispensario\InventarioMedicinasController::class);
                Route::get('medicinas/{id}/kardex',
                    [\App\Http\Controllers\Dispensario\InventarioMedicinasController::class, 'kardex']);
            });

        // Fichas de salud ocupacional
        Route::prefix('fichas-sso')
            ->middleware('role:medico|admin-dispensario')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Dispensario\FichaSaludOcupacionalController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Dispensario\FichaSaludOcupacionalController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\Dispensario\FichaSaludOcupacionalController::class, 'show']);
            });
    });

        // Admin TI
    Route::prefix('admin')
        ->middleware('role:admin-ti')
        ->group(function () {
            Route::get('usuarios/sugerir-usuario-ti',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'sugerirUsuarioTi'])
                ->name('usuarios.sugerirUsuarioTi');

            Route::get('usuarios/sin-servidor',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'sinServidor'])
                ->name('usuarios.sinServidor');
            Route::post('usuarios/{usuario}/toggle-activo',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'toggleActivo'])
                ->name('usuarios.toggleActivo');
            
            Route::get('usuarios-roles',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'roles'])
                ->name('usuarios.roles');
            Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
            Route::post('usuarios/{usuario}/restablecer-contrasena',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'restablecerContrasena']
            );

            Route::get('permisos',
                [\App\Http\Controllers\Admin\PermisoController::class, 'index'])
                ->name('admin.permisos.index');

            Route::get('usuarios/{id}/permisos',
                [\App\Http\Controllers\Admin\PermisoController::class, 'permisosUsuario'])
                ->name('admin.usuarios.permisos');

            Route::post('usuarios/{id}/permisos',
                [\App\Http\Controllers\Admin\PermisoController::class, 'sincronizarPermisosUsuario'])
                ->name('admin.usuarios.permisos.sincronizar');
        });

    // Módulo 15 — Capacitación
    Route::prefix('capacitacion')
        ->group(function () {
            Route::apiResource('planes', \App\Http\Controllers\Capacitacion\PlanCapacitacionController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::apiResource('cursos', \App\Http\Controllers\Capacitacion\CursoController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::post('cursos/{curso}/inscribir',
                [\App\Http\Controllers\Capacitacion\CursoController::class, 'inscribir']);
            Route::post('cursos/{curso}/evaluar',
                [\App\Http\Controllers\Capacitacion\CursoController::class, 'evaluar'])
                ->middleware('role:admin-uath|asistente-uath');
            Route::get('certificados/{servidorId}',
                [\App\Http\Controllers\Capacitacion\CertificadoCapacitacionController::class, 'show']);
            Route::post('certificados/{inscripcionId}/generar',
                [\App\Http\Controllers\Capacitacion\CertificadoCapacitacionController::class, 'generar'])
                ->middleware('role:admin-uath|asistente-uath');
        });

    // Módulo 16 — Actividades Laborales
    Route::prefix('actividades')
        ->group(function () {
            Route::apiResource('/', \App\Http\Controllers\Actividades\ActividadLaboralController::class)
                ->parameters(['' => 'actividad']); // Para que las rutas resource funcionen con /
            Route::get('por-unidad',
                [\App\Http\Controllers\Actividades\ActividadLaboralController::class, 'porUnidad'])
                ->middleware('role:jefe-unidad|director|admin-uath');
            Route::post('exportar-informe',
                [\App\Http\Controllers\Actividades\ActividadLaboralController::class, 'exportarInforme']);
        });

    // Módulo 17 — Bienestar y Clima
    Route::prefix('bienestar')
        ->group(function () {
            Route::apiResource('planes', \App\Http\Controllers\Bienestar\PlanBienestarController::class)
                ->middleware('role:admin-uath|asistente-uath');
            Route::get('encuestas',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'index']);
            Route::post('encuestas',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'store'])
                ->middleware('role:admin-uath');
            Route::post('encuestas/{encuesta}/responder',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'responder']);
            Route::get('encuestas/{encuesta}/resultados',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'resultados'])
                ->middleware('role:admin-uath|director|maxima-autoridad');
        });

    // Módulo 18 — Reportería e Inteligencia Institucional
    Route::prefix('reporteria')
        ->group(function () {
            Route::get('dashboard',
                [\App\Http\Controllers\Reporteria\DashboardController::class, 'kpis'])
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');

            // Reportes Ad Hoc y Configuración
            Route::get('configuraciones',
                [\App\Http\Controllers\Reporteria\ReporteController::class, 'indexConfiguraciones'])
                ->middleware('role:admin-uath|auditor|director');
            Route::post('configuraciones',
                [\App\Http\Controllers\Reporteria\ReporteController::class, 'storeConfiguracion'])
                ->middleware('role:admin-uath|auditor|director');
            Route::post('ad-hoc',
                [\App\Http\Controllers\Reporteria\ReporteController::class, 'generarAdHoc'])
                ->middleware('role:admin-uath|auditor|director');

            // Reportes Asíncronos (Background)
            Route::post('background',
                [\App\Http\Controllers\Reporteria\ReporteController::class, 'solicitarFondo'])
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');
            Route::get('background/{job_id}',
                [\App\Http\Controllers\Reporteria\ReporteController::class, 'estadoFondo'])
                ->middleware('role:admin-uath|maxima-autoridad|auditor|director');
        });


});
