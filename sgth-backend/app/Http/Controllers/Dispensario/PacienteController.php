<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\PacienteServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PacienteController extends Controller
{
    public function __construct(
        private readonly PacienteServiceInterface $pacienteService,
    ) {}

    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'cedula' => ['required', 'string', 'min:5'],
        ]);

        $resultado = $this->pacienteService->buscarPorCedula(
            $request->string('cedula')->trim()->value()
        );

        return ApiResponse::ok($resultado);
    }
}
