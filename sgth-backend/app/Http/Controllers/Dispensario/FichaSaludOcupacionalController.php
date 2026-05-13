<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class FichaSaludOcupacionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de fichas de salud');
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::created([], 'Ficha registrada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de ficha');
    }
}