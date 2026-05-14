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
        $user = $request->user();
        $query = PermisoServidor::with('servidor')->orderBy('created_at', 'desc');

        // Si no es admin/asistente de UATH, restringir vista
        if (!($user->hasRole(['admin-uath', 'asistente-uath']))) {
            if ($user->servidor && $user->servidor->puesto && $user->servidor->puesto->es_jefe) {
                // Jefe ve los de su unidad
                $unidadId = $user->servidor->unidad_administrativa_id;
                $query->whereHas('servidor', function ($q) use ($unidadId) {
                    $q->where('unidad_administrativa_id', $unidadId);
                });
            } else {
                // Empleado normal ve solo los suyos
                $servidorId = $user->servidor->id ?? 0;
                $query->where('servidor_id', $servidorId);
            }
        }

        $permisos = $query->get();
        return ApiResponse::ok($permisos, 'Listado de permisos');
    }

    public function store(StorePermisoServidorRequest $request)
    {
        // Se asume envío de servidor_id por UATH, o si no se envía, asume el autenticado
        $servidorId = $request->input('servidor_id') ?? ($request->user()->servidor->id ?? 1);

        $permiso = $this->permisoService->crear($request->validated(), $servidorId);
        
        return ApiResponse::created($permiso, 'Permiso solicitado correctamente.');
    }

    public function show(int $id, Request $request)
    {
        $permiso = PermisoServidor::with(['servidor', 'folioPermiso'])->findOrFail($id);
        
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
        
        if ($permiso->estado !== EstadoPermiso::PENDIENTE) {
            return ApiResponse::error('Solo se pueden anular permisos en estado PENDIENTE.', 400);
        }

        $permiso->estado = EstadoPermiso::ANULADO->value;
        $permiso->anulado_por = $request->user()->id;
        $permiso->anulado_en = now();
        $permiso->save();

        return ApiResponse::ok($permiso, 'Permiso anulado correctamente.');
    }
}
