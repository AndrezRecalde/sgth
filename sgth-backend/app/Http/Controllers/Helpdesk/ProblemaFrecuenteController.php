<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\GuardarProblemaFrecuenteRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\ProblemaFrecuente;
use App\Services\Helpdesk\ProblemaFrecuenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los problemas frecuentes de «Pedir ayuda a TIC», en la configuración de la
 * mesa de ayuda. No se borran: uno que ya no sirve se desactiva.
 */
final class ProblemaFrecuenteController extends Controller
{
    public function __construct(private readonly ProblemaFrecuenteService $servicio) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->servicio->listar(), 'Problemas frecuentes.');
    }

    public function store(GuardarProblemaFrecuenteRequest $request): JsonResponse
    {
        $problema = $this->servicio->crear($request->validated());

        return ApiResponse::created($this->servicio->fila($problema), 'Problema frecuente registrado.');
    }

    public function update(GuardarProblemaFrecuenteRequest $request, ProblemaFrecuente $problema): JsonResponse
    {
        $problema = $this->servicio->actualizar($problema, $request->validated());

        return ApiResponse::ok(
            $this->servicio->fila($problema),
            $problema->activo ? 'Problema frecuente actualizado.' : 'Problema frecuente desactivado: ya no se ofrece al pedir ayuda.',
        );
    }

    public function ordenar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ], [], ['ids' => 'orden']);

        $this->servicio->ordenar($datos['ids']);

        return ApiResponse::ok($this->servicio->listar(), 'Orden guardado.');
    }
}
