<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\Psicosocial\CuestionarioPsicosocialData;
use App\Services\Sso\PsicosocialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints públicos (sin autenticación): el cuestionario de evaluación de riesgo psicosocial
 * es anónimo por diseño (Guía MDT), accesible por código de campaña, sin login de servidor.
 */
final class RespuestaPsicosocialController extends Controller
{
    public function __construct(
        private readonly PsicosocialService $psicosocialService,
    ) {}

    public function cuestionario(string $codigo): JsonResponse
    {
        $cuestionario = $this->psicosocialService->obtenerCuestionario($codigo);
        return ApiResponse::ok($cuestionario, 'Cuestionario obtenido exitosamente.');
    }

    public function store(Request $request, string $codigo): JsonResponse
    {
        $opciones = CuestionarioPsicosocialData::datosGeneralesOpciones();

        $validated = $request->validate([
            'area_trabajo' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['area_trabajo']))],
            'nivel_instruccion' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['nivel_instruccion']))],
            'antiguedad' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['antiguedad']))],
            'rango_edad' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['rango_edad']))],
            'autoidentificacion_etnica' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['autoidentificacion_etnica']))],
            'genero' => ['nullable', 'string', 'in:' . implode(',', array_keys($opciones['genero']))],
            'respuestas' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $esperados = range(1, 58);
                    $recibidos = array_map('intval', array_keys($value));
                    sort($recibidos);
                    if ($recibidos !== $esperados) {
                        $fail('Debe responder los 58 ítems del cuestionario (1 a 58).');
                    }
                },
            ],
            'respuestas.*' => ['required', 'integer', 'between:1,4'],
        ]);

        $respuesta = $this->psicosocialService->registrarRespuesta($codigo, $validated);
        return ApiResponse::created($respuesta, 'Respuesta registrada exitosamente. Gracias por su colaboración.');
    }
}
