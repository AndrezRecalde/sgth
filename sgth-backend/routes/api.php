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

    // Admin TI
    Route::prefix('admin')
        ->middleware('role:admin-ti')
        ->group(function () {
            Route::apiResource('usuarios', \App\Http\Controllers\Admin\UsuarioController::class);
            Route::post('usuarios/{usuario}/restablecer-contrasena',
                [\App\Http\Controllers\Admin\UsuarioController::class, 'restablecerContrasena']
            );
        });
});
