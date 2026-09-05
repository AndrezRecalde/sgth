<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    /**
     * Respuesta exitosa genérica.
     */
    public static function ok(
        mixed $data = null,
        string $mensaje = 'Operación exitosa.',
        int $codigo = 200,
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $data,
            'meta'    => $meta,
        ], $codigo);
    }

    /**
     * Respuesta paginada.
     */
    public static function paginado(
        LengthAwarePaginator $data,
        string $mensaje = 'Consulta exitosa.',
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => $data->items(),
            'meta'    => [
                'pagina_actual' => $data->currentPage(),
                'por_pagina'    => $data->perPage(),
                'total'         => $data->total(),
                'ultima_pagina' => $data->lastPage(),
                'desde'         => $data->firstItem(),
                'hasta'         => $data->lastItem(),
            ],
        ], 200);
    }

    /**
     * Recurso creado exitosamente.
     */
    public static function created(
        mixed $data = null,
        string $mensaje = 'Registro creado exitosamente.',
    ): JsonResponse {
        return self::ok($data, $mensaje, 201);
    }

    /**
     * Operación exitosa sin contenido (eliminaciones).
     */
    public static function noContent(
        string $mensaje = 'Registro eliminado exitosamente.',
    ): JsonResponse {
        return response()->json([
            'exito'   => true,
            'mensaje' => $mensaje,
            'datos'   => null,
            'meta'    => null,
        ], 200);
    }

    /**
     * Error genérico.
     */
    public static function error(
        string $mensaje = 'Ha ocurrido un error.',
        mixed $errores = null,
        int $codigo = 422,
    ): JsonResponse {
        return response()->json([
            'exito'   => false,
            'mensaje' => $mensaje,
            'datos'   => null,
            'errores' => $errores,
        ], $codigo);
    }

    /**
     * No autorizado (403).
     */
    public static function noAutorizado(
        string $mensaje = 'No tiene autorización para realizar esta acción.',
    ): JsonResponse {
        return self::error($mensaje, null, 403);
    }

    /**
     * No encontrado (404).
     */
    public static function noEncontrado(
        string $mensaje = 'El recurso solicitado no fue encontrado.',
    ): JsonResponse {
        return self::error($mensaje, null, 404);
    }

    /**
     * Error de servidor (500).
     */
    public static function errorServidor(
        string $mensaje = 'Error interno del servidor.',
    ): JsonResponse {
        return self::error($mensaje, null, 500);
    }
}
