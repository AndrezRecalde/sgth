<?php
namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\MotivoVacacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\StoreVacacionRequest;
use App\Http\Requests\Asistencia\UpdateVacacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Vacacion;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function __construct(
        private VacacionServiceInterface $vacacionService
    ) {}

    public function index(Request $request)
    {
        $query = Vacacion::with([
            'servidor',
            'jefe',
            'creadoPor',
            'unidadAdministrativa',
        ])->orderBy('created_at', 'desc');

        // ── Filtros ──────────────────────────────────────
        if ($request->filled('folio')) {
            $query->where('folio', 'ilike', '%' . $request->folio . '%');
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('motivo')) {
            $query->where('motivo', $request->motivo);
        }

        if ($request->filled('servidor_id')) {
            $query->where('servidor_id', $request->servidor_id);
        }

        if ($request->filled('unidad_administrativa_id')) {
            $query->where(
                'unidad_administrativa_id',
                $request->unidad_administrativa_id
            );
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_inicio', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_inicio', '<=', $request->fecha_hasta);
        }

        $perPage    = $request->integer('per_page', 20);
        $vacaciones = $query->paginate($perPage);

        return ApiResponse::ok($vacaciones, 'Listado de solicitudes de vacaciones');
    }

    public function saldo(int $servidorId)
    {
        $saldo = $this->vacacionService->calcularSaldoActual($servidorId);
        return ApiResponse::ok(['saldo_dias' => $saldo], 'Saldo de vacaciones calculado.');
    }

    public function store(StoreVacacionRequest $request)
    {
        $servidorId = $request->input('servidor_id')
            ?? ($request->user()->servidor->id ?? null);

        if (!$servidorId) {
            return ApiResponse::error('No se identificó el servidor.', 422);
        }

        $datos = array_merge($request->validated(), [
            'creado_por'    => $request->user()->id,
            'fecha_emision' => now()->toDateString(),
        ]);

        $vacacion = $this->vacacionService->solicitar($datos, $servidorId);
        return ApiResponse::created($vacacion, 'Solicitud de vacaciones generada.');
    }

    public function update(UpdateVacacionRequest $request, int $id)
    {
        $vacacion = Vacacion::with(['servidor'])->findOrFail($id);

        $estadoAnterior = $vacacion->estado instanceof \App\Enums\EstadoVacacion
            ? $vacacion->estado->value
            : (string) $vacacion->estado;

        $nuevoEstado = $request->validated('estado');
        $vacacion->estado = $nuevoEstado;

        if ($nuevoEstado === 'aprobada') {
            $vacacion->aprobado_por = $request->user()->id;
            $vacacion->save();

            // Descontar del período solo si:
            // 1. El estado anterior NO era aprobada (evitar doble descuento)
            // 2. El motivo descuenta vacaciones
            if ($estadoAnterior !== 'aprobada') {
                $motivo = $vacacion->motivo instanceof \App\Enums\MotivoVacacion
                    ? $vacacion->motivo
                    : \App\Enums\MotivoVacacion::tryFrom((string)$vacacion->motivo);

                if ($motivo?->descuentaVacaciones() && $vacacion->servidor_id) {
                    $anio = \Carbon\Carbon::parse($vacacion->fecha_inicio)->year;
                    app(\App\Services\Asistencia\PeriodoVacacionService::class)
                        ->descontarDias(
                            $vacacion->servidor_id,
                            (float) $vacacion->dias_solicitados,
                            $anio
                        );
                }
            }
        } else {
            $vacacion->save();
        }

        return ApiResponse::ok(
            $vacacion->fresh(['servidor', 'jefe', 'creadoPor']),
            "Solicitud resuelta como {$nuevoEstado}."
        );
    }

    public function exportar(int $id): mixed
    {
        $vacacion = \App\Models\Asistencia\Vacacion::with([
            'servidor.puesto.cargo',
            'servidor',
            'jefe',
            'personaReemplaza',
            'unidadAdministrativa',
            'creadoPor',
            'aprobadoPor',
        ])->findOrFail($id);

        $pdf = app('dompdf.wrapper')
            ->setPaper('letter', 'portrait')
            ->loadView('vacaciones.vacacion-pdf', [
                'vacacion' => $vacacion,
            ]);

        $folio = $vacacion->folio ?? $vacacion->id;
        return $pdf->download("vacacion_{$folio}.pdf");
    }
}
