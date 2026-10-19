<?php

namespace App\Http\Controllers\Autoservicio;

use App\Enums\TipoSolicitudCarga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\PresentarSolicitudCargaRequest;
use App\Http\Resources\Autoservicio\MiCargaFamiliarResource;
use App\Http\Resources\Expediente\SolicitudCargaFamiliarResource;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\SolicitudCargaFamiliarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las cargas familiares de «Mi perfil» (docs/diseno-cargas-familiares-portal.md,
 * 5): las del servidor y lo que pidió sobre ellas. Siempre las de la cuenta.
 * Nada de lo que pide cuenta hasta que lo valida Talento Humano.
 */
final class MisCargasFamiliaresController extends Controller
{
    public function __construct(private readonly SolicitudCargaFamiliarService $solicitudes) {}

    public function index(Request $request): JsonResponse
    {
        $servidorId = (int) ($request->user()->servidor_id ?? 0);

        // Los recursos van sin `resolve()`: así Scramble infiere su forma y
        // el Portal tiene los tipos generados.
        return ApiResponse::ok([
            'cargas'      => MiCargaFamiliarResource::collection($this->solicitudes->cargasDelServidor($servidorId)),
            'solicitudes' => SolicitudCargaFamiliarResource::collection($this->solicitudes->delServidor($servidorId)),
        ], 'Sus cargas familiares.');
    }

    public function store(PresentarSolicitudCargaRequest $request): JsonResponse
    {
        $solicitud = $this->solicitudes->presentar(
            $request->user(),
            $request->safe()->except('documento'),
            $request->file('documento'),
        );

        $mensaje = $solicitud->tipo === TipoSolicitudCarga::ALTA
            ? 'Solicitud enviada. Talento Humano la revisará; hasta entonces, su familiar no puede atenderse en el Dispensario.'
            : 'Solicitud enviada. Talento Humano la revisará.';

        return ApiResponse::created(new SolicitudCargaFamiliarResource($solicitud), $mensaje);
    }

    public function retirar(Request $request, int $solicitud): JsonResponse
    {
        return ApiResponse::ok(
            new SolicitudCargaFamiliarResource($this->solicitudes->retirar($request->user(), $solicitud)),
            'Solicitud retirada.'
        );
    }

    /** El documento que adjuntó. */
    public function documento(Request $request, int $solicitud): StreamedResponse
    {
        return $this->solicitudes->descargar($this->solicitudes->deLaCuenta($request->user(), $solicitud));
    }
}
