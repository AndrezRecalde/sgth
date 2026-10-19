<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Resources\Expediente\SolicitudCargaFamiliarResource;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\SolicitudCargaFamiliar;
use App\Services\Expediente\SolicitudCargaFamiliarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La bandeja «Cargas por validar» de Talento Humano
 * (docs/diseno-cargas-familiares-portal.md, 6.2): lo que los servidores piden
 * desde «Mi perfil». La ruta la limita a quien gestiona las cargas en el
 * expediente; nadie resuelve las de su propia familia.
 */
final class SolicitudCargaFamiliarController extends Controller
{
    public function __construct(private readonly SolicitudCargaFamiliarService $solicitudes) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'servidor_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::ok(
            SolicitudCargaFamiliarResource::collection(
                $this->solicitudes->porValidar(isset($datos['servidor_id']) ? (int) $datos['servidor_id'] : null)
            ),
            'Cargas familiares por validar.'
        );
    }

    public function validar(Request $request, int $solicitud): JsonResponse
    {
        return ApiResponse::ok(
            new SolicitudCargaFamiliarResource($this->solicitudes->validar($solicitud, $request->user())),
            'Solicitud validada: el cambio ya consta en el expediente.'
        );
    }

    public function rechazar(Request $request, int $solicitud): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['motivo' => 'motivo del rechazo']);

        return ApiResponse::ok(
            new SolicitudCargaFamiliarResource($this->solicitudes->rechazar($solicitud, $datos['motivo'], $request->user())),
            'Solicitud rechazada.'
        );
    }

    /** El documento que adjuntó el servidor. */
    public function documento(int $solicitud): StreamedResponse
    {
        return $this->solicitudes->descargar(SolicitudCargaFamiliar::findOrFail($solicitud));
    }
}
