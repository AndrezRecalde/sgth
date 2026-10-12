<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\EstadoSolicitudAccion;
use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\SolicitudAccionPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\SolicitudAccionPersonal;
use App\Services\Expediente\SolicitudAccionPersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Las solicitudes del portal, del lado de Talento Humano (fase 6.3b): las
 * atiende preparando la acción o las rechaza con el motivo. Las rutas las
 * limitan a quien prepara acciones; nadie atiende la suya.
 */
class SolicitudAccionPersonalController extends Controller
{
    public function __construct(private readonly SolicitudAccionPersonalService $solicitudes) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoSolicitudAccion::class)],
        ]);

        return ApiResponse::ok(
            SolicitudAccionPersonalResource::collection($this->solicitudes->paraTalentoHumano($datos['estado'] ?? null))
                ->resolve($request),
            'Solicitudes del portal.'
        );
    }

    public function preparar(Request $request, int $solicitud): JsonResponse
    {
        $atendida = $this->solicitudes->preparar($solicitud, $request->user());

        return ApiResponse::ok(
            (new SolicitudAccionPersonalResource($atendida))->resolve($request),
            "Se preparó el borrador de la acción: está en la bandeja."
        );
    }

    public function rechazar(Request $request, int $solicitud): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['motivo' => 'motivo del rechazo']);

        return ApiResponse::ok(
            (new SolicitudAccionPersonalResource($this->solicitudes->rechazar($solicitud, $datos['motivo'], $request->user())))
                ->resolve($request),
            'Solicitud rechazada.'
        );
    }

    /** El respaldo que presentó el servidor. */
    public function documento(SolicitudAccionPersonal $solicitud): mixed
    {
        return Storage::disk('local')->download($solicitud->documento_ruta, $solicitud->documento_nombre);
    }
}
