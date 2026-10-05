<?php

namespace App\Http\Controllers\Seleccion;

use App\Contracts\Seleccion\SeleccionServiceInterface;
use App\Enums\EstadoConvocatoria;
use App\Enums\TipoNombramiento;
use App\Enums\TipoProcesoConvocatoria;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Services\Seleccion\CalificacionService;
use App\Models\Estructura\Puesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Enum;

final class ConvocatoriaController extends Controller
{
    public function __construct(private readonly SeleccionServiceInterface $seleccionService) {}

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
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

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

        // Sin `all=1` (2026-10-04): devolvía todas las convocatorias de una vez
        // y ninguna pantalla lo usaba. Y `per_page` con tope, para que nadie
        // pida cien mil filas en una sola respuesta.
        return ApiResponse::ok(
            $query->paginate(min(max($request->integer('per_page', 15), 1), 100))
        );
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

    /**
     * Solo en borrador, y sin tocar el estado (decisión de TH, 2026-10-05).
     *
     * Antes aceptaba `estado` libre: dos de sus valores (`en_proceso`,
     * `cerrada`) no existen y daban 500, una finalizada podía volver a borrador
     * y borrarse, y cambiar fechas o vacantes de un concurso ya publicado
     * alteraba lo que se anunció. El estado avanza solo con sus acciones:
     * publicar, declarar ganadores, incorporar, declarar desierta o cancelar.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $convocatoria = Convocatoria::findOrFail($id);

        $this->assertNoEsContenedor($convocatoria);

        if ($convocatoria->estado !== EstadoConvocatoria::BORRADOR) {
            throw new ReglaNegocioException(
                'Solo se edita una convocatoria en borrador: una vez publicada, sus condiciones ya se anunciaron.'
            );
        }

        $datos = $request->validate([
            'puesto_id'      => ['sometimes', 'integer', 'exists:puestos,id'],
            'titulo'         => ['sometimes', 'string', 'max:255'],
            'descripcion'    => ['sometimes', 'string'],
            'bases_concurso' => ['nullable', 'array'],
            'tipo'           => ['sometimes', Rule::in(['interna', 'externa', 'mixta'])],
            'fecha_inicio'   => ['sometimes', 'date'],
            'fecha_fin'      => ['sometimes', 'date'],
            'vacantes'       => ['sometimes', 'integer', 'min:1'],
        ]);

        // El orden de las fechas se mira con lo que quedaría guardado: si
        // solo llega una, la otra es la de la convocatoria.
        $inicio = $datos['fecha_inicio'] ?? $convocatoria->fecha_inicio?->toDateString();
        $fin = $datos['fecha_fin'] ?? $convocatoria->fecha_fin?->toDateString();
        if ($inicio && $fin && $fin <= $inicio) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de cierre debe ser posterior a la de inicio.',
            ]);
        }

        $convocatoria->update([
            ...$datos,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok(
            $convocatoria->load(['puesto.cargo', 'puesto.unidadAdministrativa']),
            'Convocatoria actualizada.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $convocatoria = Convocatoria::findOrFail($id);

        $this->assertNoEsContenedor($convocatoria);

        if ($convocatoria->estado !== EstadoConvocatoria::BORRADOR) {
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

        $this->assertNoEsContenedor($convocatoria);

        if ($convocatoria->estado !== EstadoConvocatoria::BORRADOR) {
            return ApiResponse::error(
                'Solo se pueden publicar convocatorias en borrador.', null, 422
            );
        }

        $criterios = CalificacionService::criteriosVigentes($id);
        if ($criterios->isEmpty()) {
            throw new ReglaNegocioException(
                'No se puede publicar una convocatoria sin criterios de evaluación configurados. Aplique una plantilla o agregue criterios primero.'
            );
        }

        // Sobre 100, como el puntaje aprobatorio de 70 (decisión 5 de TH,
        // 2026-10-05). Antes se publicaba con criterios que sumaban 40 o 160.
        CalificacionService::assertSuman100($criterios, 'publicar la convocatoria');

        $convocatoria->update([
            'estado'     => 'publicada',
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok($convocatoria, 'Convocatoria publicada.');
    }

    /**
     * Declara desierto o cancela un concurso publicado, con su motivo.
     */
    public function cerrar(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in([
                EstadoConvocatoria::DESIERTA->value, EstadoConvocatoria::CANCELADA->value,
            ])],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $convocatoria = $this->seleccionService->cerrarSinGanadores(
            $id, EstadoConvocatoria::from($datos['estado']), $datos['motivo'], $request->user()->id
        );

        return ApiResponse::ok(
            $convocatoria->load(['puesto.cargo', 'puesto.unidadAdministrativa']),
            $convocatoria->estado === EstadoConvocatoria::DESIERTA
                ? 'La convocatoria fue declarada desierta.'
                : 'La convocatoria fue cancelada.'
        );
    }

    /**
     * Los cuatro contenedores de reclutamiento express son permanentes: se
     * crean con la base y no se editan, publican, cierran ni borran.
     */
    private function assertNoEsContenedor(Convocatoria $convocatoria): void
    {
        if ($convocatoria->es_contenedor_permanente) {
            throw new ReglaNegocioException(
                'Los contenedores de reclutamiento express son permanentes y no se modifican.'
            );
        }
    }
}
