<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\DisponibilidadServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DisponibilidadController extends Controller
{
    public function __construct(
        private readonly DisponibilidadServiceInterface $service,
    ) {}

    /**
     * A quién se le puede asignar un turno de esta atención.
     *
     * Lo pide Recepción al abrir un turno, así que no lo limita el rol clínico:
     * lo que se devuelve es la lista de profesionales, no el estado de nadie en
     * particular. `hay_disponibles` dice si la lista sale de quienes se marcaron
     * o si, al no haber ninguno, se está mostrando a todo el personal del rol.
     */
    public function paraAtencion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_atencion' => ['required', Rule::enum(EspecialidadAtencion::class)],
        ]);

        $resultado = $this->service->listarParaAtencion(
            EspecialidadAtencion::from($datos['tipo_atencion'])
        );

        return ApiResponse::ok(
            $resultado['personal'],
            'Personal para la atención.',
            200,
            ['hay_disponibles' => $resultado['hay_disponibles']],
        );
    }

    public function miEstado(Request $request): JsonResponse
    {
        $disponible = $this->service->obtenerEstado(
            $request->user()->id
        );

        return ApiResponse::ok(['disponible' => $disponible]);
    }

    public function alternar(Request $request): JsonResponse
    {
        $disponible = $this->service->alternar(
            $request->user()->id
        );

        $mensaje = $disponible
            ? 'Ahora estás disponible para atención.'
            : 'Ya no estás disponible para atención.';

        return ApiResponse::ok(
            ['disponible' => $disponible], $mensaje
        );
    }
}
