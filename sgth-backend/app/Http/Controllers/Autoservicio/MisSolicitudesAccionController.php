<?php

namespace App\Http\Controllers\Autoservicio;

use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\PresentarSolicitudAccionRequest;
use App\Http\Resources\Expediente\SolicitudAccionPersonalResource;
use App\Http\Responses\ApiResponse;
use App\Services\Expediente\SolicitudAccionPersonalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Las solicitudes del servidor desde el portal: renuncia, licencia sin
 * remuneración y jubilación (diseño, 9.13; fase 6.3b). Siempre sobre el
 * servidor de la cuenta.
 */
class MisSolicitudesAccionController extends Controller
{
    public function __construct(private readonly SolicitudAccionPersonalService $solicitudes) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok(
            SolicitudAccionPersonalResource::collection(
                $this->solicitudes->delServidor((int) ($request->user()?->servidor_id ?? 0))
            )->resolve($request),
            'Mis solicitudes.'
        );
    }

    public function store(PresentarSolicitudAccionRequest $request): JsonResponse
    {
        $solicitud = $this->solicitudes->presentar(
            $request->user(),
            $request->safe()->except('archivo'),
            $request->file('archivo'),
        );

        return ApiResponse::ok(
            (new SolicitudAccionPersonalResource($solicitud))->resolve($request),
            "Solicitud {$solicitud->folio} presentada: Talento Humano la atenderá.",
            201
        );
    }

    public function retirar(Request $request, int $solicitud): JsonResponse
    {
        return ApiResponse::ok(
            (new SolicitudAccionPersonalResource($this->solicitudes->retirar($request->user(), $solicitud)))->resolve($request),
            'Solicitud retirada.'
        );
    }

    /** El respaldo que presentó. */
    public function documento(Request $request, int $solicitud): mixed
    {
        $s = $this->solicitudes->deLaCuenta($request->user(), $solicitud);

        return Storage::disk('local')->download($s->documento_ruta, $s->documento_nombre);
    }
}
