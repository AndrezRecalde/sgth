<?php

namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\PermisoServiceInterface;
use App\Enums\EstadoPermiso;
use App\Enums\Permiso;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\MotivoPermisoRequest;
use App\Http\Requests\Asistencia\StorePermisoServidorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PermisoServidorController extends Controller
{
    /** Techo del paginador: `?per_page=100000` traía la tabla entera. */
    private const PER_PAGE_MAX = 100;

    public function __construct(private PermisoServiceInterface $permisoService) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('verAny', PermisoServidor::class);

        $query = PermisoServidor::with([
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

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
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
            $query->whereDate('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha', '<=', $request->fecha_hasta);
        }

        $this->aplicarAlcance($query, $user);

        $perPage = min(max($request->integer('per_page', 20), 1), self::PER_PAGE_MAX);
        $permisos = $query->paginate($perPage);

        // La observación se tapa fila por fila con la misma regla que en el
        // detalle: un listado no puede filtrar lo que el detalle protege.
        $permisos->getCollection()->each(
            fn (PermisoServidor $p) => $this->ocultarObservacionSiCorresponde($p, $user)
        );

        return ApiResponse::ok($permisos, 'Listado de permisos');
    }

    public function store(StorePermisoServidorRequest $request)
    {
        $servidorId = $request->input('servidor_id')
            ?? ($request->user()->servidor->id ?? null);

        if (!$servidorId) {
            return ApiResponse::error(
                'No se identificó el servidor.', 422
            );
        }

        // Solo LOSEP accede al módulo de permisos. Se comprueba en positivo y
        // no descartando el Código del Trabajo: con esa forma, el régimen de
        // servicios profesionales —agregado el 2026-08-29— habría entrado por
        // omisión, y un contrato civil no tiene jornada que permisar.
        $servidor = \App\Models\Expediente\Servidor::findOrFail($servidorId);
        $regimen = $servidor->regimen_laboral instanceof \App\Enums\RegimenLaboral
            ? $servidor->regimen_laboral
            : \App\Enums\RegimenLaboral::tryFrom(
                (string) ($servidor->regimen_laboral ?? 'losep')
            );

        if (! $regimen?->accedeAPermisos()) {
            return ApiResponse::error(
                'El régimen '.($regimen?->etiqueta() ?? 'del servidor').
                ' no tiene acceso al módulo de permisos.',
                422
            );
        }

        $datos = array_merge($request->validated(), [
            'creado_por' => $request->user()->id,
        ]);

        $permiso = $this->permisoService->crear($datos, $servidorId);

        return ApiResponse::created(
            $permiso, 'Permiso solicitado correctamente.'
        );
    }

    public function show(int $id, Request $request)
    {
        $permiso = PermisoServidor::with(['servidor'])->findOrFail($id);

        $this->authorize('ver', $permiso);

        $this->ocultarObservacionSiCorresponde($permiso, $request->user());

        return ApiResponse::ok($permiso, 'Detalle del permiso');
    }

    public function confirmar(string $folio, Request $request)
    {
        // Confirmación por Recepción escaneando el código
        $permiso = $this->permisoService->confirmarRecepcion($folio, $request->user()->id);
        return ApiResponse::ok($permiso, 'Permiso confirmado exitosamente por Recepción.');
    }

    public function validar(int $id, Request $request)
    {
        // Validación de Trabajo Social para Enfermedad y Calamidad
        $permiso = $this->permisoService->validarTrabajoSocial($id, $request->user()->id);
        return ApiResponse::ok($permiso, 'Permiso validado por Trabajo Social.');
    }

    public function rechazar(int $id, MotivoPermisoRequest $request)
    {
        $this->authorize('rechazar', PermisoServidor::findOrFail($id));

        $permiso = $this->permisoService->rechazar(
            $id, $request->user()->id, $request->validated()['motivo']
        );

        return ApiResponse::ok($permiso, 'Permiso rechazado por Recepción.');
    }

    public function revertirConfirmacion(int $id, MotivoPermisoRequest $request)
    {
        $this->authorize('revertir', PermisoServidor::findOrFail($id));

        $permiso = $this->permisoService->revertirConfirmacion(
            $id, $request->user()->id, $request->validated()['motivo']
        );

        return ApiResponse::ok(
            $permiso,
            'Confirmación revertida. El permiso vuelve a PENDIENTE y se devolvió el saldo descontado.'
        );
    }

    public function anular(int $id, Request $request)
    {
        $permiso = PermisoServidor::findOrFail($id);

        $this->authorize('anular', $permiso);

        $estadoActual = $permiso->estado instanceof EstadoPermiso
            ? $permiso->estado->value
            : (string) $permiso->estado;

        if ($estadoActual !== EstadoPermiso::PENDIENTE->value) {
            return ApiResponse::error('Solo se pueden anular permisos en estado PENDIENTE.', 400);
        }

        $permiso->estado = EstadoPermiso::ANULADO->value;
        $permiso->anulado_por = $request->user()->id;
        $permiso->anulado_en = now();
        $permiso->save();

        return ApiResponse::ok($permiso, 'Permiso anulado correctamente.');
    }

    public function exportar(int $id, Request $request): mixed
    {
        $permiso = PermisoServidor::with([
            'servidor.puesto.cargo',
            'jefe',
            'unidadAdministrativa',
            'creadoPor',
        ])->findOrFail($id);

        $this->authorize('exportar', $permiso);

        // El PDF lleva el motivo impreso. Quien no puede leerlo en pantalla
        // tampoco puede sacarlo en papel: la vista recibe la decisión ya
        // tomada y no vuelve a razonarla.
        $pdf = app('dompdf.wrapper')
            ->loadView('permisos.permiso-pdf', [
                'permiso' => $permiso,
                'mostrarObservacion' => $request->user()->can('verObservacion', $permiso),
            ]);

        $nombreArchivo = "permiso_{$permiso->folio}.pdf";

        return $pdf->download($nombreArchivo);
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    /**
     * Recorta el listado a lo que el usuario tiene derecho a ver.
     *
     * Antes esto miraba dos roles escritos a mano (`admin-uath`,
     * `asistente-uath`) y mandaba a todo lo demás a «solo lo mío», con dos
     * efectos: máxima autoridad y auditoría —que sí tienen
     * `ver-permisos-todos` en el seeder— no veían nada, y Recepción y Trabajo
     * Social tenían endpoints para confirmar y validar pero ninguna pantalla
     * donde encontrar qué confirmar o validar. Ahora manda la matriz de
     * permisos, que es donde esas decisiones ya estaban tomadas.
     */
    private function aplicarAlcance(Builder $query, User $user): void
    {
        if ($user->can(Permiso::VER_PERMISOS_TODOS->value)) {
            return;
        }

        $servidorId = $user->servidor_id;
        $unidadId   = $user->servidor?->unidad_administrativa_id;

        $esJefe = $unidadId && (
            $user->can(Permiso::VER_ASISTENCIA_UNIDAD->value)
            || (bool) ($user->servidor?->puesto?->es_jefe)
        );

        $query->where(function (Builder $q) use ($user, $servidorId, $unidadId, $esJefe) {
            // Lo propio, siempre.
            $q->where('servidor_id', $servidorId ?? 0);

            if ($esJefe) {
                $q->orWhere(function (Builder $sub) use ($unidadId) {
                    $sub->where('unidad_administrativa_id', $unidadId)
                        ->orWhere(function (Builder $sinUnidad) use ($unidadId) {
                            $sinUnidad->whereNull('unidad_administrativa_id')
                                ->whereHas('servidor', fn ($s) => $s->where(
                                    'unidad_administrativa_id', $unidadId
                                ));
                        });
                });
            }

            // Recepción trabaja contra el documento físico: necesita ver lo
            // que está pendiente de confirmar, de cualquier unidad.
            if ($user->can(Permiso::CONFIRMAR_RECEPCION->value)) {
                $q->orWhere('estado', EstadoPermiso::PENDIENTE->value);
            }

            // Trabajo Social solo valida enfermedad y calamidad.
            if ($user->can(Permiso::VALIDAR_TRABAJO_SOCIAL->value)) {
                $q->orWhereIn('tipo', [
                    TipoPermiso::ENFERMEDAD->value,
                    TipoPermiso::CALAMIDAD->value,
                ]);
            }
        });
    }

    private function ocultarObservacionSiCorresponde(PermisoServidor $permiso, User $user): void
    {
        if (! $user->can('verObservacion', $permiso)) {
            $permiso->makeHidden('observacion');
        }
    }
}
