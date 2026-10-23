<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreOrdenExamenRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\OrdenExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Las órdenes de exámenes: las emite el médico o el odontólogo en su
 * consulta, las ve todo el personal clínico y las anula quien las emitió o
 * la administración (docs/diseno-examenes-medicos.md, fase 1).
 */
final class OrdenExamenController extends Controller
{
    /** Lo que acompaña a una orden: sus exámenes, quién la pidió y por qué. */
    private const RELACIONES = [
        'items.examen:id,nombre,area,etiqueta_nota,sensible',
        'solicitante:id,usuario_ti,email,servidor_id',
        'solicitante.servidor:id,nombre,apellido',
        'diagnosticoCie10:id,codigo,descripcion',
    ];

    public function __construct(
        private readonly OrdenExamenServiceInterface $ordenes
    ) {}

    /**
     * Las órdenes de UN paciente, y de una consulta suya si se pide. Como el
     * listado de resultados: sin paciente no hay listado.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'historia_clinica_id' => ['required', 'integer'],
            'consulta_medica_id'  => ['nullable', 'integer'],
        ], [
            'historia_clinica_id.required' => 'Indique de qué paciente son las órdenes.',
        ]);

        $ordenes = OrdenExamen::with(self::RELACIONES)
            ->where('historia_clinica_id', $request->integer('historia_clinica_id'))
            ->when($request->filled('consulta_medica_id'), fn ($q) => $q->where(
                'consulta_medica_id', $request->integer('consulta_medica_id')
            ))
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::ok($ordenes, 'Órdenes de exámenes.');
    }

    public function store(StoreOrdenExamenRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $consulta = ConsultaMedica::findOrFail($datos['consulta_medica_id']);

        if ($consulta->medico_id !== $request->user()->id) {
            return ApiResponse::error(
                'Solo quien atendió la consulta puede pedir exámenes en ella: ' .
                'la orden sale con su firma.',
                null,
                403
            );
        }

        $orden = $this->ordenes->emitir(
            [
                ...Arr::except($datos, ['items']),
                // Del paciente de la consulta, no de lo que mande la pantalla.
                'historia_clinica_id' => $consulta->historia_clinica_id,
            ],
            $datos['items'],
            $request->user()->id,
        );

        return ApiResponse::created(
            $orden->refresh()->load(self::RELACIONES),
            'Orden de exámenes emitida.'
        );
    }

    public function show(int $orden): JsonResponse
    {
        $orden = OrdenExamen::with([
            ...self::RELACIONES,
            'historiaClinica.servidor:id,nombre,apellido,cedula',
            'historiaClinica.cargaFamiliar:id,nombres,apellidos,cedula',
            'anulador:id,usuario_ti,email,servidor_id',
        ])->findOrFail($orden);

        return ApiResponse::ok($orden);
    }

    /** La anula quien la emitió o la administración del Dispensario. */
    public function anular(Request $request, int $orden): JsonResponse
    {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $existente = OrdenExamen::findOrFail($orden);
        $usuario = $request->user();

        if ($existente->solicitado_por !== $usuario->id && ! $usuario->hasRole('admin-dispensario')) {
            return ApiResponse::error(
                'Solo quien emitió la orden o la administración del Dispensario pueden anularla.',
                null,
                403
            );
        }

        $anulada = $this->ordenes->anular(
            $orden,
            $request->string('motivo_anulacion')->value(),
            $usuario->id,
        );

        return ApiResponse::ok(
            $anulada->refresh()->load(self::RELACIONES),
            'Orden de exámenes anulada.'
        );
    }
}
