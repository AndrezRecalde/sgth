<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Responses\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Si Sirha7, el biométrico, no responde, nada cambió en el SGTH (la
 * transacción se deshace) y se dice así, con un 503: no es un error de quien
 * aprueba. Lo usan la aprobación de permisos y la de certificados médicos.
 */
trait RespondeSinBiometrico
{
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

            Log::error('Sirha7 no respondió: ' . $e->getMessage());

            return ApiResponse::error($mensaje, codigo: 503);
        }
    }
}
