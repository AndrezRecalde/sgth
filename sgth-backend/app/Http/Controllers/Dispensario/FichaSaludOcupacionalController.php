<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreFichaSaludOcupacionalRequest;
use App\Http\Requests\Dispensario\UpdateFichaSaludOcupacionalRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\FemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FichaSaludOcupacionalController extends Controller
{
    public function __construct(
        private readonly FemoService $femoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $fichas = $this->femoService->listar(
            $request->only([
                'servidor_id', 'tipo_ficha', 'aptitud',
                'fecha_desde', 'fecha_hasta', 'per_page',
            ])
        );

        return ApiResponse::ok($fichas);
    }

    public function store(StoreFichaSaludOcupacionalRequest $request): JsonResponse
    {
        $ficha = $this->femoService->registrar(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($ficha, 'Ficha FEMO registrada correctamente.');
    }

    public function show(int $id): JsonResponse
    {
        $ficha = $this->femoService->obtener($id);

        return ApiResponse::ok($ficha);
    }

    public function update(UpdateFichaSaludOcupacionalRequest $request, int $id): JsonResponse
    {
        $ficha = $this->femoService->actualizar(
            $id,
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::ok($ficha, 'Ficha FEMO actualizada correctamente.');
    }
}
