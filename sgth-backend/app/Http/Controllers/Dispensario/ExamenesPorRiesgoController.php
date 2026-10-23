<?php

namespace App\Http\Controllers\Dispensario;

use App\Catalogos\FactoresRiesgoMsp;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\RiesgoExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * El mapa riesgo → exámenes (fase 5 de docs/diseno-examenes-medicos.md, D6):
 * qué exámenes pide cada factor de riesgo del puesto. Lo arma el médico
 * ocupacional o la administración; la ficha ocupacional lo usa para proponer
 * la orden de la evaluación.
 */
final class ExamenesPorRiesgoController extends Controller
{
    /** Los factores que tienen exámenes, cada uno con los suyos en el orden de la hoja. */
    public function index(): JsonResponse
    {
        $mapa = RiesgoExamen::with('examen:id,nombre,area,orden,sensible,activo')
            ->orderBy('categoria')
            ->orderBy('factor')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (RiesgoExamen $r) => "{$r->categoria}|{$r->factor}")
            ->map(fn ($filas) => [
                'categoria' => $filas->first()->categoria,
                'factor'    => $filas->first()->factor,
                'examenes'  => $filas->pluck('examen')->filter()->values(),
            ])
            ->values();

        return ApiResponse::ok($mapa, 'Exámenes por factor de riesgo.');
    }

    /** Los exámenes de un factor: reemplaza los que tenía. Una lista vacía lo deja sin ninguno. */
    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'categoria'  => ['required', 'string', Rule::in(array_keys(FactoresRiesgoMsp::catalogo()))],
            'factor'     => ['required', 'string', 'max:150'],
            'examenes'   => ['present', 'array', 'max:30'],
            'examenes.*' => ['integer', 'distinct',
                Rule::exists('catalogo_examenes', 'id')->where('activo', true),
            ],
        ], [
            'examenes.*.exists' => 'Ese examen no está en el catálogo activo.',
        ]);

        validator($datos, [])->after(function (Validator $v) use ($datos) {
            if (! in_array($datos['factor'], FactoresRiesgoMsp::factoresDe($datos['categoria']), true)) {
                $v->errors()->add('factor', 'Ese factor no es de la categoría indicada en el formulario del MSP.');
            }

            // Lo que propone el riesgo del puesto es ocupacional: un examen
            // sensible no se exige para trabajar (D8, MDT-2024-198).
            $sensibles = CatalogoExamen::whereIn('id', $datos['examenes'])->where('sensible', true)->pluck('nombre');
            if ($sensibles->isNotEmpty()) {
                $v->errors()->add(
                    'examenes',
                    "Un factor de riesgo no puede pedir exámenes sensibles: quite {$sensibles->implode(', ')}."
                );
            }
        })->validate();

        DB::transaction(function () use ($datos) {
            RiesgoExamen::where('categoria', $datos['categoria'])->where('factor', $datos['factor'])->delete();

            foreach ($datos['examenes'] as $examenId) {
                RiesgoExamen::create([
                    'categoria'          => $datos['categoria'],
                    'factor'             => $datos['factor'],
                    'catalogo_examen_id' => $examenId,
                ]);
            }
        });

        return ApiResponse::ok([
            'categoria' => $datos['categoria'],
            'factor'    => $datos['factor'],
            'examenes'  => CatalogoExamen::whereIn('id', $datos['examenes'])->enOrden()
                ->get(['id', 'nombre', 'area', 'orden', 'sensible', 'activo']),
        ], 'Exámenes del factor guardados.');
    }
}
