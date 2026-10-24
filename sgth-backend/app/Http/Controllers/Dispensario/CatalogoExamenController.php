<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\AreaExamen;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreCatalogoExamenRequest;
use App\Http\Requests\Dispensario\UpdateCatalogoExamenRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CatalogoExamen;
use App\Services\Dispensario\ExamenSensibleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de exámenes: lo lee todo el personal clínico para pedir, y lo
 * mantiene la administración del Dispensario (P2).
 */
final class CatalogoExamenController extends Controller
{
    public function __construct(private readonly ExamenSensibleService $sensibles) {}

    /**
     * El catálogo entero, en el orden de la hoja, con las áreas en `meta`.
     *
     * No pagina a propósito: son unos doscientos exámenes y el modal de
     * solicitud los enseña todos a la vez, como casillas por área. Los
     * inactivos solo los ve la administración, que es quien los reactiva.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'incluir_inactivos' => ['nullable', 'boolean'],
        ]);

        $conInactivos = $request->boolean('incluir_inactivos')
            && $request->user()->hasRole('admin-dispensario');

        $examenes = CatalogoExamen::query()
            ->when(! $conInactivos, fn ($q) => $q->activos())
            ->enOrden()
            ->get();

        return ApiResponse::ok(
            $examenes,
            'Catálogo de exámenes.',
            meta: ['areas' => AreaExamen::opciones()],
        );
    }

    /** Un examen nuevo va al final de su área. */
    public function store(StoreCatalogoExamenRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $examen = CatalogoExamen::create([
            ...$datos,
            'orden' => $this->siguienteOrden($datos['area']),
        ]);

        // Recargado: `activo` y `sensible` los pone la base por defecto, y sin
        // esto la respuesta no los traía.
        return ApiResponse::created($examen->refresh(), 'Examen agregado al catálogo.');
    }

    /**
     * Corrige un examen, lo retira (`activo: false`) o lo devuelve.
     *
     * No hay borrado: las órdenes que ya lo pidieron lo siguen nombrando. Si
     * cambia de área, pasa al final de la nueva.
     */
    public function update(UpdateCatalogoExamenRequest $request, int $examen): JsonResponse
    {
        $examen = CatalogoExamen::findOrFail($examen);
        $datos = $request->validated();

        if (isset($datos['area']) && $datos['area'] !== $examen->area->value) {
            $datos['orden'] = $this->siguienteOrden($datos['area']);
        }

        // Al pasar a sensible sale de todo lo ocupacional, y se dice de dónde.
        $pasaASensible = ($datos['sensible'] ?? false) && ! $examen->sensible;

        $quitado = DB::transaction(function () use ($examen, $datos, $pasaASensible) {
            $examen->update($datos);

            return $pasaASensible
                ? $this->sensibles->quitarDeLoOcupacional($examen)
                : ['perfiles' => [], 'riesgos' => []];
        });

        return ApiResponse::ok(
            $examen->fresh(),
            trim("Examen «{$examen->nombre}» actualizado. " . ExamenSensibleService::mensaje($quitado)),
            meta: ['quitado_de' => $quitado],
        );
    }

    private function siguienteOrden(string $area): int
    {
        return (int) CatalogoExamen::where('area', $area)->max('orden') + 1;
    }
}
