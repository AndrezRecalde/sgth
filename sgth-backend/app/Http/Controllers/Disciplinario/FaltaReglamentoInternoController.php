<?php

namespace App\Http\Controllers\Disciplinario;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disciplinario\GuardarFaltaReglamentoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Disciplinario\FaltaReglamentoInterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El catálogo de faltas del reglamento interno de los obreros (diseño de
 * Acciones de Personal, 9.15; fase 4.4). Talento Humano lo carga con su copia
 * del reglamento aprobado; sin él, al obrero no se le sanciona.
 *
 * No se borra una falta: se desactiva. Y la que ya se imputó en un
 * procedimiento no se reescribe, porque el procedimiento y su sanción la citan;
 * si el reglamento cambia, se desactiva y se carga la redacción nueva.
 */
class FaltaReglamentoInternoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $faltas = FaltaReglamentoInterno::query()
            ->when($request->boolean('activas'), fn ($q) => $q->activas())
            ->orderByDesc('activo')
            ->orderBy('articulo')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($faltas, 'Faltas del reglamento interno.');
    }

    public function store(GuardarFaltaReglamentoRequest $request): JsonResponse
    {
        $falta = FaltaReglamentoInterno::create([
            ...$this->datos($request),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::created($falta->fresh(), 'Falta agregada al reglamento interno.');
    }

    public function update(GuardarFaltaReglamentoRequest $request, FaltaReglamentoInterno $falta): JsonResponse
    {
        $falta->fill($this->datos($request));

        if ($falta->isDirty() && array_diff(array_keys($falta->getDirty()), ['activo']) !== [] && $falta->enUso()) {
            throw new ReglaNegocioException(
                'Esta falta ya se imputó en un procedimiento, que la cita: no se reescribe. Desactívela y cargue la '
                    .'redacción nueva.'
            );
        }

        $falta->updated_by = $request->user()->id;
        $falta->save();

        return ApiResponse::ok($falta->fresh(), 'Falta del reglamento interno actualizada.');
    }

    /** @return array<string, mixed> */
    private function datos(GuardarFaltaReglamentoRequest $request): array
    {
        $datos = $request->validated();
        $multa = (bool) ($datos['admite_multa'] ?? false);

        return [
            'articulo'                    => trim($datos['articulo']),
            'descripcion'                 => trim($datos['descripcion']),
            'gravedad'                    => filled($datos['gravedad'] ?? null) ? trim($datos['gravedad']) : null,
            'admite_amonestacion_verbal'  => (bool) ($datos['admite_amonestacion_verbal'] ?? false),
            'admite_amonestacion_escrita' => (bool) ($datos['admite_amonestacion_escrita'] ?? false),
            'admite_multa'                => $multa,
            // Sin multa no hay tope que guardar.
            'porcentaje_maximo_multa'     => $multa ? $datos['porcentaje_maximo_multa'] : null,
            'activo'                      => (bool) ($datos['activo'] ?? true),
        ];
    }
}
