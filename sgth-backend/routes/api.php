<?php

use Illuminate\Support\Facades\Route;

// ── Rutas públicas (sin autenticación) ────────────────────────────
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
    });
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
