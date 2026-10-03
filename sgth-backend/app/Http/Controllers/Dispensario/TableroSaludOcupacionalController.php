<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\TableroSaludOcupacionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableroSaludOcupacionalController extends Controller
{
    public function __construct(
        private readonly TableroSaludOcupacionalService $tablero,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        return ApiResponse::ok(
            $this->tablero->resumen((int) ($datos['anio'] ?? now()->year))
        );
    }
}
