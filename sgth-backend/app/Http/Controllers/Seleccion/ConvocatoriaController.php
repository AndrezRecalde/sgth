<?php

namespace App\Http\Controllers\Seleccion;

use App\Enums\TipoNombramiento;
use App\Enums\TipoProcesoConvocatoria;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Estructura\Puesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class ConvocatoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Los contenedores permanentes de Reclutamiento Express no son
        // convocatorias que se listen, abran o cierren: son bandejas por
        // modalidad. Mezclarlos aquí los hacía navegables como si fueran un
        // concurso, con acciones de concurso que el backend después rechaza.
        // Se administran en /sgth/reclutamiento/express.
        $query = Convocatoria::with([
            'puesto.cargo',
            'puesto.unidadAdministrativa',
        ])
            ->where('es_contenedor_permanente', false)
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('puesto_id')) {
            $query->where('puesto_id', $request->integer('puesto_id'));
        }

        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->where(function ($sq) use ($q) {
                $sq->where('titulo', 'ilike', "%{$q}%")
                   ->orWhere('codigo', 'ilike', "%{$q}%");
            });
        }

        $convocatorias = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->integer('per_page', 15));

        return ApiResponse::ok($convocatorias);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'puesto_id'       => ['required', 'integer', 'exists:puestos,id'],
            'titulo'          => ['required', 'string', 'max:255'],
            'descripcion'     => ['required', 'string'],
            'bases_concurso'  => ['nullable', 'array'],
            'tipo_proceso'    => ['required', new Enum(TipoProcesoConvocatoria::class)],
            // Formal: concurso público real, con plazo — sigue obligatorio.
            // Express: no hay plazo real que abrir, se autocompleta abajo.
            'fecha_inicio'    => ['required_if:tipo_proceso,formal', 'nullable', 'date'],
            'fecha_fin'       => ['required_if:tipo_proceso,formal', 'nullable', 'date', 'after:fecha_inicio'],
            'tipo'            => ['required', Rule::in(['interna', 'externa', 'mixta'])],
            'vacantes'        => ['required', 'integer', 'min:1'],
            'tipo_nombramiento_previsto' => [
                'required_if:tipo_proceso,express',
                // Sin esto, formal + tipo_nombramiento_previsto pasa la
                // validación y revienta con un 500 crudo (SQLSTATE 23514)
                // al chocar con el CHECK de coherencia de la migración —
                // mejor rechazarlo aquí con un mensaje claro.
                'prohibited_if:tipo_proceso,formal',
                'nullable',
                Rule::in([
                    TipoNombramiento::PROVISIONAL->value,
                    TipoNombramiento::SERVICIOS_OCASIONALES->value,
                    TipoNombramiento::SERVICIOS_PROFESIONALES->value,
                    TipoNombramiento::CODIGO_TRABAJO->value,
                ]),
            ],
        ]);

        if ($datos['tipo_proceso'] === TipoProcesoConvocatoria::EXPRESS->value) {
            $datos['fecha_inicio'] ??= now()->toDateString();
            $datos['fecha_fin']    ??= now()->toDateString();
        }

        $anio       = now()->year;
        $correlativo = Convocatoria::whereYear('created_at', $anio)->count() + 1;
        $tipo        = strtoupper(substr($datos['tipo'], 0, 3));

        $convocatoria = Convocatoria::create([
            ...$datos,
            'codigo'     => "CONV-{$tipo}-{$anio}-" . str_pad($correlativo, 4, '0', STR_PAD_LEFT),
            'estado'     => 'borrador',
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created(
            $convocatoria->load(['puesto.cargo', 'puesto.unidadAdministrativa']),
            'Convocatoria registrada correctamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::with([
            'puesto.cargo',
            'puesto.unidadAdministrativa',
            'puesto.grupoOcupacional',
            'puesto.actividadesActivas',
            'postulantes.evaluacion',
            'postulantes.documentos',
        ])->findOrFail($id);

        return ApiResponse::ok($convocatoria);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::findOrFail($id);

        if ($convocatoria->estado->value === 'cerrada') {
            return ApiResponse::error(
                'No se puede modificar una convocatoria cerrada.', null, 422
            );
        }

        $datos = $request->validate([
            'titulo'         => ['sometimes', 'string', 'max:255'],
            'descripcion'    => ['sometimes', 'string'],
            'bases_concurso' => ['nullable', 'array'],
            'fecha_inicio'   => ['sometimes', 'date'],
            'fecha_fin'      => ['sometimes', 'date'],
            'estado'         => ['sometimes', Rule::in([
                'borrador', 'publicada', 'en_proceso',
                'cerrada', 'desierta',
            ])],
            'vacantes'       => ['sometimes', 'integer', 'min:1'],
        ]);

        $convocatoria->update([
            ...$datos,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok(
            $convocatoria->load(['puesto.cargo']),
            'Convocatoria actualizada.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::findOrFail($id);

        if ($convocatoria->estado->value !== 'borrador') {
            return ApiResponse::error(
                'Solo se pueden eliminar convocatorias en borrador.', null, 422
            );
        }

        $convocatoria->delete();
        return ApiResponse::ok([], 'Convocatoria eliminada.');
    }

    public function publicar(Request $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::findOrFail($id);

        if ($convocatoria->estado->value !== 'borrador') {
            return ApiResponse::error(
                'Solo se pueden publicar convocatorias en borrador.', null, 422
            );
        }

        if (! CriterioEvaluacion::where('convocatoria_id', $id)->exists()) {
            throw new ReglaNegocioException(
                'No se puede publicar una convocatoria sin criterios de evaluación configurados. Aplique una plantilla o agregue criterios primero.'
            );
        }

        $convocatoria->update([
            'estado'     => 'publicada',
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok($convocatoria, 'Convocatoria publicada.');
    }
}
