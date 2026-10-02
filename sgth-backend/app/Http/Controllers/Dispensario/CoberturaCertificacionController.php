<?php

namespace App\Http\Controllers\Dispensario;

use App\Exports\CoberturaCertificacionExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\CoberturaCertificacionRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\CoberturaCertificacionService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El tablero de cobertura de las evaluaciones médicas ocupacionales.
 *
 * Responde a la pregunta que el módulo no podía responder: a quién le toca la
 * evaluación y no la tiene. Las pantallas existentes listan solicitudes, y una
 * solicitud solo existe si alguien se acordó de pedirla.
 */
final class CoberturaCertificacionController extends Controller
{
    public function __construct(
        private readonly CoberturaCertificacionService $service,
    ) {
    }

    public function index(CoberturaCertificacionRequest $request): JsonResponse
    {
        $resultado = $this->service->listar($request->filtros());

        // El resumen viaja junto a la página, no en una segunda petición: es
        // la misma consulta y el tablero no se puede leer a medias.
        return ApiResponse::ok([
            ...$resultado['datos']->toArray(),
            'resumen' => $resultado['resumen'],
        ]);
    }

    public function excel(CoberturaCertificacionRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new CoberturaCertificacionExport($this->service->exportar($request->filtros())),
            'cobertura_certificaciones_'.now()->format('Ymd_His').'.xlsx'
        );
    }
}
