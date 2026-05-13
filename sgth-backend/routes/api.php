<?php

use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
    });

    // Endpoint público para escanear el QR del permiso físico
    Route::get('permisos/verificar/{folio}', [\App\Http\Controllers\Asistencia\FolioPermisoController::class, 'verificar']);

    // Endpoint público protegido criptográficamente mediante firmas temporales (URL firmada)
    Route::get('sgd/documentos/{documento}/descargar', [\App\Http\Controllers\Sgd\DocumentoInstitucionalController::class, 'descargar'])
        ->name('sgd.documentos.descargar');
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

    // Módulo 01: Estructura Organizacional
    Route::prefix('estructura')->group(function () {
        Route::get('organigrama', \App\Http\Controllers\Estructura\OrganigramaController::class);
        Route::apiResource('unidades-administrativas', \App\Http\Controllers\Estructura\UnidadAdministrativaController::class);
        Route::apiResource('puestos', \App\Http\Controllers\Estructura\PuestoController::class);
    });

    // Módulo 02: Expediente Digital
    Route::prefix('expediente')->group(function () {
        Route::apiResource('servidores', \App\Http\Controllers\Expediente\ServidorController::class);
        
        Route::post('servidores/{servidor}/documentos', [\App\Http\Controllers\Expediente\DocumentoServidorController::class, 'store']);
        Route::get('servidores/{servidor}/movimientos', [\App\Http\Controllers\Expediente\MovimientoPersonalController::class, 'index']);

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
            Route::put('{id}/anular', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'anular']);
            
            Route::post('confirmar/{folio}', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'confirmar']);
            Route::post('{id}/validar-ts', [\App\Http\Controllers\Asistencia\PermisoServidorController::class, 'validar']);
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
    });

    // Módulo 07: Selección e Incorporación
    Route::prefix('seleccion')->group(function () {
        Route::post('postulantes/{id}/calificar', [\App\Http\Controllers\Seleccion\SeleccionController::class, 'calificar']);
        Route::post('convocatorias/{id}/declarar-ganador', [\App\Http\Controllers\Seleccion\SeleccionController::class, 'declararGanador']);
    });

    // Módulo 08: Evaluación del Desempeño
    Route::prefix('evaluacion')->group(function () {
        Route::post('evaluaciones/{evaluacionId}/servidor/{servidorId}', [\App\Http\Controllers\Evaluacion\EvaluacionController::class, 'registrarResultado']);
    });

    // Módulo 09: Viáticos
    Route::prefix('viaticos')->group(function () {
        Route::post('servidor/{servidorId}/solicitar', [\App\Http\Controllers\Viatico\ViaticoController::class, 'solicitar']);
        Route::post('{viaticoId}/liquidar', [\App\Http\Controllers\Viatico\ViaticoController::class, 'liquidar']);
    });

    // Módulo 14: Disciplinario
    Route::prefix('disciplinario')->group(function () {
        Route::post('sumarios/{id}/resolver', [\App\Http\Controllers\Disciplinario\DisciplinarioController::class, 'resolver'])
            ->middleware('role:admin-uath');
    });

    // Módulo 10 — SSO
    Route::prefix('sso')
        ->middleware('role:admin-uath,asistente-uath,auditor')
        ->group(function () {
            Route::apiResource('riesgos', \App\Http\Controllers\Sso\RiesgoLaboralController::class);
            Route::apiResource('accidentes', \App\Http\Controllers\Sso\AccidenteTrabajoController::class);
            Route::apiResource('equipos-proteccion', \App\Http\Controllers\Sso\EquipoProteccionController::class);
        });

    // Módulo 11 — Dispensario Médico
    Route::prefix('dispensario')->group(function () {

        // Agenda — accesible para todo el personal del dispensario
        Route::apiResource('agenda', \App\Http\Controllers\Dispensario\AgendaController::class)
            ->middleware('role:medico,odontologo,enfermera,admin-dispensario');

        // Historias clínicas — SOLO personal médico
        Route::prefix('historias-clinicas')
            ->middleware('role:medico,odontologo,enfermera,admin-dispensario')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\Dispensario\HistoriaClinicaController::class, 'show']);
            });

        // Consultas médicas — SOLO médicos y odontólogos crean,
        // todo el personal médico puede ver
        Route::prefix('consultas')
            ->middleware('role:medico,odontologo,enfermera,admin-dispensario')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'store']);
                Route::get('{id}', [\App\Http\Controllers\Dispensario\ConsultaMedicaController::class, 'show']);
            });

        // Recetas — médicos emiten, enfermeras y admin despachan
        Route::prefix('recetas')->group(function () {
            Route::post('/', [\App\Http\Controllers\Dispensario\RecetaController::class, 'store'])
                ->middleware('role:medico,odontologo');
            Route::get('{id}', [\App\Http\Controllers\Dispensario\RecetaController::class, 'show'])
                ->middleware('role:medico,odontologo,enfermera,admin-dispensario');
            Route::post('{id}/despachar', [\App\Http\Controllers\Dispensario\RecetaController::class, 'despachar'])
                ->middleware('role:enfermera,admin-dispensario');
        });

        // Inventario — todo el personal del dispensario
        Route::prefix('inventario')
            ->middleware('role:medico,odontologo,enfermera,admin-dispensario')
            ->group(function () {
                Route::apiResource('medicinas', \App\Http\Controllers\Dispensario\InventarioMedicinasController::class);
                Route::get('medicinas/{id}/kardex',
                    [\App\Http\Controllers\Dispensario\InventarioMedicinasController::class, 'kardex']);
            });

        // Fichas de salud ocupacional
        Route::prefix('fichas-sso')
            ->middleware('role:medico,admin-dispensario')
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
            Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
            Route::post('usuarios/{usuario}/restablecer-contrasena',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'restablecerContrasena']
            );
        });

    // Módulo 15 — Capacitación
    Route::prefix('capacitacion')
        ->group(function () {
            Route::apiResource('planes', \App\Http\Controllers\Capacitacion\PlanCapacitacionController::class)
                ->middleware('role:admin-uath,asistente-uath');
            Route::apiResource('cursos', \App\Http\Controllers\Capacitacion\CursoController::class)
                ->middleware('role:admin-uath,asistente-uath');
            Route::post('cursos/{curso}/inscribir',
                [\App\Http\Controllers\Capacitacion\CursoController::class, 'inscribir']);
            Route::post('cursos/{curso}/evaluar',
                [\App\Http\Controllers\Capacitacion\CursoController::class, 'evaluar'])
                ->middleware('role:admin-uath,asistente-uath');
            Route::get('certificados/{servidorId}',
                [\App\Http\Controllers\Capacitacion\CertificadoCapacitacionController::class, 'show']);
            Route::post('certificados/{inscripcionId}/generar',
                [\App\Http\Controllers\Capacitacion\CertificadoCapacitacionController::class, 'generar'])
                ->middleware('role:admin-uath,asistente-uath');
        });

    // Módulo 16 — Actividades Laborales
    Route::prefix('actividades')
        ->group(function () {
            Route::apiResource('/', \App\Http\Controllers\Actividades\ActividadLaboralController::class)
                ->parameters(['' => 'actividad']); // Para que las rutas resource funcionen con /
            Route::get('por-unidad',
                [\App\Http\Controllers\Actividades\ActividadLaboralController::class, 'porUnidad'])
                ->middleware('role:jefe-unidad,director,admin-uath');
            Route::post('exportar-informe',
                [\App\Http\Controllers\Actividades\ActividadLaboralController::class, 'exportarInforme']);
        });

    // Módulo 17 — Bienestar y Clima
    Route::prefix('bienestar')
        ->group(function () {
            Route::apiResource('planes', \App\Http\Controllers\Bienestar\PlanBienestarController::class)
                ->middleware('role:admin-uath,asistente-uath');
            Route::get('encuestas',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'index']);
            Route::post('encuestas',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'store'])
                ->middleware('role:admin-uath');
            Route::post('encuestas/{encuesta}/responder',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'responder']);
            Route::get('encuestas/{encuesta}/resultados',
                [\App\Http\Controllers\Bienestar\EncuestaClimaController::class, 'resultados'])
                ->middleware('role:admin-uath,director,maxima-autoridad');
        });
});
