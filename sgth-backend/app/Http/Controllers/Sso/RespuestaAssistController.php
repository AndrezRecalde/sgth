<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sso\Assist\CuestionarioAssistData;
use App\Services\Sso\AssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints públicos (sin autenticación): el tamizaje ASSIST es anónimo por diseño
 * (Instructivo MDT-MSP-2019-038, Fase 4), accesible únicamente por código de campaña,
 * sin login de servidor.
 */
final class RespuestaAssistController extends Controller
{
    public function __construct(
        private readonly AssistService $assistService,
    ) {}

    public function cuestionario(string $codigo): JsonResponse
    {
        $cuestionario = $this->assistService->obtenerCuestionario($codigo);
        return ApiResponse::ok($cuestionario, 'Cuestionario obtenido exitosamente.');
    }

    public function store(Request $request, string $codigo): JsonResponse
    {
        $opcionesFrecuencia3m = array_keys(CuestionarioAssistData::opcionesFrecuencia3m());
        $opcionesFrecuenciaVida = array_keys(CuestionarioAssistData::opcionesFrecuenciaVida());
        $sustanciasInfo = CuestionarioAssistData::sustancias();

        $validated = $request->validate([
            'sustancias' => [
                // 'present' (no 'required'/'min:1'): un arreglo vacío es una respuesta válida
                // e intencional — "no he consumido ninguna sustancia" (P1 negativa para todas,
                // ver manual ASSIST Fig. 1: "detenga la entrevista"), no una omisión accidental.
                'present',
                'array',
                function ($attribute, $value, $fail) use ($opcionesFrecuencia3m, $opcionesFrecuenciaVida, $sustanciasInfo) {
                    foreach ($value as $codigoSustancia => $respuestas) {
                        if (! array_key_exists($codigoSustancia, $sustanciasInfo)) {
                            $fail("La sustancia \"{$codigoSustancia}\" no es válida.");
                            continue;
                        }
                        if (! is_array($respuestas) || ! isset($respuestas['p2']) || ! in_array($respuestas['p2'], $opcionesFrecuencia3m, true)) {
                            $fail("Debe indicar la frecuencia de consumo en los últimos 3 meses (P2) para \"{$codigoSustancia}\".");
                            continue;
                        }
                        if ($respuestas['p2'] !== 'nunca') {
                            foreach (['p3', 'p4'] as $pregunta) {
                                if (! isset($respuestas[$pregunta]) || ! in_array($respuestas[$pregunta], $opcionesFrecuencia3m, true)) {
                                    $fail("Debe responder la pregunta {$pregunta} para \"{$codigoSustancia}\".");
                                }
                            }
                            if ($sustanciasInfo[$codigoSustancia]['incluye_pregunta_5']
                                && (! isset($respuestas['p5']) || ! in_array($respuestas['p5'], $opcionesFrecuencia3m, true))) {
                                $fail("Debe responder la pregunta P5 para \"{$codigoSustancia}\".");
                            }
                        }
                        foreach (['p6', 'p7'] as $pregunta) {
                            if (! isset($respuestas[$pregunta]) || ! in_array($respuestas[$pregunta], $opcionesFrecuenciaVida, true)) {
                                $fail("Debe responder la pregunta {$pregunta} para \"{$codigoSustancia}\".");
                            }
                        }
                    }
                },
            ],
            'uso_inyectable' => ['nullable', 'string', 'in:' . implode(',', $opcionesFrecuenciaVida)],
        ]);

        $respuesta = $this->assistService->registrarRespuesta($codigo, $validated);
        return ApiResponse::created($respuesta, 'Tamizaje registrado exitosamente. Gracias por su colaboración.');
    }
}
