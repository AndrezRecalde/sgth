<?php

namespace App\Http\Controllers\Asistencia;

use App\Contracts\Asistencia\PermisoServiceInterface;
use App\Enums\EstadoPermiso;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asistencia\StorePermisoServidorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;
use Illuminate\Http\Request;

class PermisoServidorController extends Controller
{
    public function __construct(private PermisoServiceInterface $permisoService) {}

    public function index(Request $request)
    {
        $user  = $request->user();
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

        // ── Control de acceso ────────────────────────────
        if (!$user->hasRole(['admin-uath', 'asistente-uath'])) {
            if (
                $user->servidor &&
                $user->servidor->puesto &&
                $user->servidor->puesto->es_jefe
            ) {
                $unidadId = $user->servidor->unidad_administrativa_id;
                $query->whereHas('servidor', function ($q) use ($unidadId) {
                    $q->where('unidad_administrativa_id', $unidadId);
                });
            } else {
                $servidorId = $user->servidor->id ?? 0;
                $query->where('servidor_id', $servidorId);
            }
        }

        $perPage  = $request->integer('per_page', 20);
        $permisos = $query->paginate($perPage);

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

        // Verificar que el servidor no sea CT
        $servidor = \App\Models\Expediente\Servidor::findOrFail($servidorId);
        $regimen = $servidor->regimen_laboral instanceof \App\Enums\RegimenLaboral
            ? $servidor->regimen_laboral->value
            : (string)($servidor->regimen_laboral ?? 'losep');

        if ($regimen === 'codigo_trabajo') {
            return ApiResponse::error(
                'Los servidores con Código del Trabajo no tienen acceso al módulo de permisos.',
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
        
        $user = $request->user();
        
        // Regla de privacidad: Jefe no puede ver motivo de permiso personal de su subordinado
        if ($permiso->tipo === TipoPermiso::PERSONAL && $user->servidor && $permiso->servidor_id !== $user->servidor->id) {
            $permiso->makeHidden('observacion');
        }

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

    public function anular(int $id, Request $request)
    {
        $permiso = PermisoServidor::findOrFail($id);
        
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

    public function exportar(int $id): mixed
    {
        $permiso = PermisoServidor::with([
            'servidor.puesto.cargo',
            'jefe',
            'unidadAdministrativa',
            'creadoPor',
        ])->findOrFail($id);

        $pdf = app('dompdf.wrapper')
            ->loadView('permisos.permiso-pdf', compact('permiso'));

        $nombreArchivo = "permiso_{$permiso->folio}.pdf";

        return $pdf->download($nombreArchivo);
    }
}
