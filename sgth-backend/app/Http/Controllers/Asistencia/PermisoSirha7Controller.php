<?php

namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\AprobarPermisoSirha7Request;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;
use App\Services\Asistencia\AprobacionPermisoSirha7Service;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Aprobar un permiso registrándolo en Sirha7, el biométrico.
 *
 * Personal y oficial los aprueba Talento Humano; enfermedad y calamidad,
 * Trabajo Social, que al aprobarlas las valida (decisión del 2026-10-08).
 * Las reglas viven en `AprobacionPermisoSirha7Service` y en los
 * procedimientos de Sirha7; aquí solo se autoriza y se responde.
 */
class PermisoSirha7Controller extends Controller
{
    public function __construct(private AprobacionPermisoSirha7Service $aprobacion) {}

    public function tipos(): JsonResponse
    {
        $this->authorize('verTiposSirha7', PermisoServidor::class);

        return $this->conBiometrico(
            fn () => ApiResponse::ok($this->aprobacion->tipos(), 'Tipos de permiso de Sirha7.'),
            'No se pudo conectar al sistema biométrico para leer los tipos de permiso.'
        );
    }

    public function previa(int $id): JsonResponse
    {
        $permiso = PermisoServidor::findOrFail($id);
        $this->authorize('aprobarSirha7', $permiso);

        return ApiResponse::ok($this->aprobacion->previa($permiso), 'Lo que se registrará en Sirha7.');
    }

    public function aprobar(int $id, AprobarPermisoSirha7Request $request): JsonResponse
    {
        $this->authorize('aprobarSirha7', PermisoServidor::findOrFail($id));

        $usuario = $request->user();

        return $this->conBiometrico(fn () => ApiResponse::ok(
            $this->aprobacion->aprobar(
                $id,
                $request->integer('leave_id'),
                $usuario->id,
                $usuario->servidor_id,
            ),
            'Permiso aprobado y registrado en Sirha7.'
        ), 'No se pudo conectar al sistema biométrico. El permiso no cambió; inténtelo de nuevo.');
    }

    /**
     * Si el biométrico no responde, nada cambió en el SGTH (la transacción se
     * deshace) y se dice así, con un 503: no es un error de quien aprueba.
     */
    private function conBiometrico(callable $accion, string $mensaje): JsonResponse
    {
        try {
            return $accion();
        } catch (\PDOException $e) {
            // Solo lo que viene del biométrico. Un fallo de la base del SGTH
            // no es «Sirha7 no responde» y sigue su camino.
            if ($e instanceof QueryException && $e->getConnectionName() !== 'sqlsrv') {
                throw $e;
            }

            Log::error('Sirha7 no respondió (permisos): ' . $e->getMessage());

            return ApiResponse::error($mensaje, codigo: 503);
        }
    }
}
