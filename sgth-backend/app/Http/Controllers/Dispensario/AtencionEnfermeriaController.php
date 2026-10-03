<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AtencionEnfermeriaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\AnularAtencionEnfermeriaRequest;
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
        // Validados antes de llegar a la consulta: una fecha mal escrita
        // acababa en un 500 de Postgres y `per_page` no tenía techo.
        $filtros = $request->validate([
            'fecha'         => ['nullable', 'date'],
            'enfermera_id'  => ['nullable', 'integer'],
            'solo_vigentes' => ['nullable', 'boolean'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $atenciones = $this->service->listar($filtros);

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

    public function anular(AnularAtencionEnfermeriaRequest $request, int $id): JsonResponse
    {
        $atencion = $this->service->anular(
            $id,
            $request->string('motivo_anulacion')->value(),
            $request->user()->id,
            $request->user()->hasRole('admin-dispensario'),
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
