<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\OrdenExamenServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreOrdenExamenRequest;
use App\Http\Responses\ApiResponse;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\OrdenExamen;
use App\Models\User;
use App\Services\Dispensario\PdfOrdenExamenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        private readonly OrdenExamenServiceInterface $ordenes,
        private readonly PdfOrdenExamenService $pdfService,
    ) {}

    /**
     * Las órdenes de UN paciente, y de una consulta suya si se pide. Como el
     * listado de resultados: sin paciente no hay listado.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            // De un paciente, o de una ficha ocupacional (fase 5).
            'historia_clinica_id'        => ['required_without:ficha_salud_ocupacional_id', 'nullable', 'integer'],
            'consulta_medica_id'         => ['nullable', 'integer'],
            'ficha_salud_ocupacional_id' => ['nullable', 'integer'],
        ], [
            'historia_clinica_id.required_without' => 'Indique de qué paciente son las órdenes.',
        ]);

        $ordenes = OrdenExamen::with(self::RELACIONES)
            ->when($request->filled('historia_clinica_id'), fn ($q) => $q->where(
                'historia_clinica_id', $request->integer('historia_clinica_id')
            ))
            ->when($request->filled('ficha_salud_ocupacional_id'), fn ($q) => $q->where(
                'ficha_salud_ocupacional_id', $request->integer('ficha_salud_ocupacional_id')
            ))
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
        $usuario = $request->user();

        // Del paciente de la consulta o de la ficha, no de lo que mande la pantalla.
        $historiaId = isset($datos['ficha_salud_ocupacional_id'])
            ? $this->historiaDeLaFicha((int) $datos['ficha_salud_ocupacional_id'], $usuario)
            : $this->historiaDeLaConsulta((int) $datos['consulta_medica_id'], $usuario);

        $orden = $this->ordenes->emitir(
            [...Arr::except($datos, ['items']), 'historia_clinica_id' => $historiaId],
            $datos['items'],
            $usuario->id,
        );

        return ApiResponse::created(
            $orden->refresh()->load(self::RELACIONES),
            'Orden de exámenes emitida.'
        );
    }

    /** La de una consulta la pide quien la atendió: la orden sale con su firma. */
    private function historiaDeLaConsulta(int $consultaId, User $usuario): int
    {
        $consulta = ConsultaMedica::findOrFail($consultaId);

        abort_if(
            $consulta->medico_id !== $usuario->id,
            403,
            'Solo quien atendió la consulta puede pedir exámenes en ella: la orden sale con su firma.'
        );

        return $consulta->historia_clinica_id;
    }

    /**
     * La de una ficha ocupacional (fase 5) la pide quien la evalúa, mientras la
     * evaluación está en curso. La historia es la del paciente por su cédula: un
     * aspirante que todavía no la tiene la estrena aquí, como en la consulta.
     */
    private function historiaDeLaFicha(int $fichaId, User $usuario): int
    {
        $ficha = FichaSaludOcupacional::with(['servidor', 'postulante', 'solicitud'])->findOrFail($fichaId);

        abort_if(
            $ficha->evaluador_id !== $usuario->id,
            403,
            'Solo el médico que evalúa la ficha pide sus exámenes: la orden sale con su firma.'
        );

        if ($ficha->solicitud?->estado !== 'en_proceso') {
            throw new ReglaNegocioException('La evaluación de esta ficha ya no está en curso: no se le piden exámenes.');
        }

        return HistoriaClinica::buscarOCrearPorCedula(
            (string) ($ficha->servidor?->cedula ?? $ficha->postulante?->cedula),
            $ficha->servidor ? 'servidor' : 'candidato',
            $ficha->servidor_id,
            null,
            $usuario->id,
        )->id;
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

    /** El impreso que el paciente lleva al laboratorio, también el de una anulada. */
    public function pdf(int $orden): Response
    {
        $resultado = $this->pdfService->generarContent($orden);

        return response($resultado['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $resultado['filename'] . '"',
        ]);
    }

    /**
     * Un examen que no va a volver: el paciente no se lo hizo o el
     * laboratorio no lo hace. Deja de esperarse, con su motivo, y la orden se
     * recalcula. Lo marca quien registra resultados (D2, P1).
     */
    public function noRealizado(Request $request, int $orden, int $item): JsonResponse
    {
        $request->validate([
            'motivo_no_realizado' => ['required', 'string', 'max:255'],
        ], [
            'motivo_no_realizado.required' => 'Indique por qué no se realizó.',
        ]);

        $this->ordenes->marcarNoRealizado(
            $orden,
            $item,
            trim($request->string('motivo_no_realizado')->value()),
        );

        return ApiResponse::ok(
            OrdenExamen::with(self::RELACIONES)->findOrFail($orden),
            'Examen marcado como no realizado.'
        );
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
