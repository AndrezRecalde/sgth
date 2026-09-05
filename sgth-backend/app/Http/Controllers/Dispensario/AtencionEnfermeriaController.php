<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreAtencionEnfermeriaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CatalogoServicioEnfermeria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AtencionEnfermeriaController extends Controller
{
    public function __construct(
        private readonly AtencionEnfermeriaServiceInterface $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $atenciones = $this->service->listar($request->all());

        return ApiResponse::ok(
            $atenciones, 'Listado de atenciones de enfermería.'
        );
    }

    public function store(
        StoreAtencionEnfermeriaRequest $request
    ): JsonResponse {
        $atencion = $this->service->registrar(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created(
            $atencion, 'Atención de enfermería registrada.'
        );
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $atencion = $this->service->anular(
            $id,
            $request->string('motivo_anulacion')->value(),
            $request->user()->id
        );

        return ApiResponse::ok(
            $atencion, 'Atención de enfermería anulada correctamente.'
        );
    }

    public function catalogo(): JsonResponse
    {
        $catalogo = CatalogoServicioEnfermeria::activos()
            ->orderBy('nombre')
            ->get();

        return ApiResponse::ok($catalogo);
    }
}
