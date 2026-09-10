<?php
namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\StoreVacacionRequest;
use App\Http\Requests\Asistencia\UpdateVacacionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VacacionController extends Controller
{
    public function __construct(
        private VacacionServiceInterface $vacacionService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('verAny', Vacacion::class);

        $query = Vacacion::with([
            'servidor',
            'jefe',
            'creadoPor',
            'unidadAdministrativa',
        ])
            // created_at es timestamp(0): sin desempate por id, dos páginas
            // del mismo resultado pueden solaparse.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

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

        $this->aplicarAlcance($query, $request->user());

        $perPage    = $request->integer('per_page', 20);
        $vacaciones = $query->paginate($perPage);

        return ApiResponse::ok($vacaciones, 'Listado de solicitudes de vacaciones');
    }

    public function saldo(int $servidorId)
    {
        $this->authorize('verSaldo', [Vacacion::class, Servidor::findOrFail($servidorId)]);

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

        $this->authorize('crear', [Vacacion::class, Servidor::findOrFail($servidorId)]);

        $datos = array_merge($request->validated(), [
            'creado_por'    => $request->user()->id,
            'fecha_emision' => now()->toDateString(),
        ]);

        $vacacion = $this->vacacionService->solicitar($datos, $servidorId);
        return ApiResponse::created($vacacion, 'Solicitud de vacaciones generada.');
    }

    public function update(UpdateVacacionRequest $request, int $id)
    {
        $this->authorize('resolver', Vacacion::findOrFail($id));

        $nuevoEstado = $request->validated('estado');

        $vacacion = $this->vacacionService->resolver(
            $id, $nuevoEstado, $request->user()
        );

        return ApiResponse::ok($vacacion, "Solicitud resuelta como {$nuevoEstado}.");
    }

    public function exportar(int $id): mixed
    {
        $vacacion = Vacacion::with([
            'servidor.puesto.cargo',
            'servidor',
            'jefe',
            'personaReemplaza',
            'unidadAdministrativa',
            'creadoPor',
            'aprobadoPor',
        ])->findOrFail($id);

        $this->authorize('exportar', $vacacion);

        $pdf = app('dompdf.wrapper')
            ->setPaper('letter', 'portrait')
            ->loadView('vacaciones.vacacion-pdf', [
                'vacacion' => $vacacion,
            ]);

        $folio = $vacacion->folio ?? $vacacion->id;
        return $pdf->download("vacacion_{$folio}.pdf");
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Recorta el listado a lo que el usuario tiene derecho a ver.
     *
     * Quien no tiene alcance institucional llegó hasta aquí con
     * `ver-vacaciones-unidad`: ve las de su unidad y las propias. Es el mismo
     * criterio que `PermisoServidorController::aplicarAlcance()`.
     */
    private function aplicarAlcance(Builder $query, User $user): void
    {
        if ($user->can('verTodas', Vacacion::class)) {
            return;
        }

        $servidorId = $user->servidor_id;
        $unidadId   = $user->servidor?->unidad_administrativa_id;

        $query->where(function (Builder $q) use ($servidorId, $unidadId) {
            $q->where('servidor_id', $servidorId ?? 0);

            if ($unidadId) {
                $q->orWhere('unidad_administrativa_id', $unidadId)
                    ->orWhere(function (Builder $sinUnidad) use ($unidadId) {
                        $sinUnidad->whereNull('unidad_administrativa_id')
                            ->whereHas('servidor', fn ($s) => $s->where(
                                'unidad_administrativa_id', $unidadId
                            ));
                    });
            }
        });
    }
}
