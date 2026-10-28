<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreAgendaMedicaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AgendaMedica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La agenda del Dispensario. Enfermería y la administración llevan la cola de
 * todos: dan los turnos, los cancelan y los marcan. El médico y el odontólogo,
 * solo los suyos. Hasta el 2026-10-26 cualquiera de los cuatro listaba la cola
 * entera y podía cancelar, marcar como no presentado, reactivar o pasar a
 * consulta el turno de otro profesional (docs/plan-roles-y-accesos.md, PR 6).
 */
final class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaServiceInterface $agendaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'fecha'         => ['nullable', 'date'],
            'medico_id'     => ['nullable', 'integer'],
            'estado'        => ['nullable', 'string', 'max:20'],
            'tipo_atencion' => ['nullable', 'string', 'max:30'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        // El profesional, su cola; pedir la de otro no la abre.
        if (! $this->llevaLaCola($request)) {
            $filtros['medico_id'] = $request->user()->id;
        }

        $agenda = $this->agendaService->listar($filtros);

        return ApiResponse::ok($agenda, 'Listado de agenda.');
    }

    public function store(
        StoreAgendaMedicaRequest $request
    ): JsonResponse {
        $cita = $this->agendaService->agendarCita(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($cita, 'Cita agendada.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($this->esDeOtro($request, $id)) {
            return $this->deOtro();
        }

        $cita = $this->agendaService->obtener($id);

        return ApiResponse::ok($cita);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->esDeOtro($request, $id)) {
            return $this->deOtro();
        }

        $cita = $this->agendaService->cancelar($id);

        return ApiResponse::ok($cita, 'Cita cancelada.');
    }

    public function turnosDelDia(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_desde'   => ['nullable', 'date'],
            'fecha_hasta'   => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            // Consultas pide la de medicina; Odontología, la suya.
            'tipo_atencion' => ['nullable', Rule::enum(EspecialidadAtencion::class)],
        ]);

        $turnos = $this->agendaService->turnosDelDia(
            $request->user()->id,
            $request->input('fecha_desde'),
            $request->input('fecha_hasta'),
            $request->input('tipo_atencion'),
        );

        return ApiResponse::ok($turnos);
    }

    public function porFolio(
        Request $request,
        string $folio
    ): JsonResponse {
        $turno = $this->agendaService->obtenerPorFolio(
            strtoupper($folio),
            $request->user()->id
        );

        return ApiResponse::ok($turno);
    }

    public function noPresentado(
        Request $request,
        int $agenda
    ): JsonResponse {
        if ($this->esDeOtro($request, $agenda)) {
            return $this->deOtro();
        }

        $turno = $this->agendaService->marcarNoPresentado(
            $agenda, $request->user()->id
        );

        return ApiResponse::ok($turno, 'Turno marcado como no presentado.');
    }

    public function reactivar(
        Request $request,
        int $agenda
    ): JsonResponse {
        if ($this->esDeOtro($request, $agenda)) {
            return $this->deOtro();
        }

        $turno = $this->agendaService->reactivar(
            $agenda, $request->user()->id
        );

        return ApiResponse::ok($turno, 'Turno reactivado correctamente.');
    }

    public function marcarEnConsulta(Request $request, int $agenda): JsonResponse
    {
        if ($this->esDeOtro($request, $agenda)) {
            return $this->deOtro();
        }

        $turno = $this->agendaService->marcarEnConsulta($agenda);

        return ApiResponse::ok($turno, 'Turno en consulta.');
    }

    public function devolverALaCola(Request $request, int $agenda): JsonResponse
    {
        $turno = $this->agendaService->devolverALaCola(
            $agenda, $request->user()->id
        );

        return ApiResponse::ok($turno, 'El turno volvió a la cola.');
    }

    /**
     * Enfermería y la administración, que dan los turnos y llevan la cola de
     * todos. Por permiso desde el PR 17b: `gestionar-agenda`, que existía sin
     * dueño.
     */
    private function llevaLaCola(Request $request): bool
    {
        return $request->user()->can(Permiso::GESTIONAR_AGENDA->value);
    }

    /**
     * Si el turno es de otro profesional y quien pregunta no lleva la cola. El
     * que no existe no lo es: sigue dando 404 más adelante.
     */
    private function esDeOtro(Request $request, int $id): bool
    {
        if ($this->llevaLaCola($request)) {
            return false;
        }

        $medico = AgendaMedica::whereKey($id)->value('medico_id');

        return $medico !== null && (int) $medico !== $request->user()->id;
    }

    private function deOtro(): JsonResponse
    {
        return ApiResponse::error('Este turno es de otro profesional.', null, 403);
    }
}
