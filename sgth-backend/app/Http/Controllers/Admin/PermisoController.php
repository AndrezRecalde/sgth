<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Admin\UsuarioServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermisoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UsuarioServiceInterface $usuarioService,
    ) {
    }

    /**
     * Retorna todos los permisos del sistema agrupados
     * por módulo, con sus roles asociados.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $permisos = Permission::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'     => $p->id,
                'nombre' => $p->name,
                'modulo' => $this->detectarModulo($p->name),
                'roles'  => $p->roles->pluck('name'),
            ])
            ->groupBy('modulo')
            ->map(fn($items, $modulo) => [
                'modulo'   => $modulo,
                'permisos' => $items->values(),
            ])
            ->values();

        return ApiResponse::ok($permisos, 'Permisos del sistema.');
    }

    /**
     * Retorna los permisos directos de un usuario
     * (no los heredados por rol).
     */
    public function permisosUsuario(int $id): JsonResponse
    {
        $user = $this->usuarioService->obtener($id);
        $this->authorize('gestionarPermisos', $user);

        $directos = $user->getDirectPermissions()
            ->map(fn($p) => [
                'id'     => $p->id,
                'nombre' => $p->name,
                'modulo' => $this->detectarModulo($p->name),
            ]);

        return ApiResponse::ok($directos, 'Permisos directos del usuario.');
    }

    /**
     * Sincroniza los permisos directos de un usuario.
     */
    public function sincronizarPermisosUsuario(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $this->usuarioService->obtener($id);
        $this->authorize('gestionarPermisos', $user);

        $datos = $request->validate([
            'permisos'   => ['array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->usuarioService->sincronizarPermisos(
            $id,
            $datos['permisos'] ?? [],
            $request->user(),
        );

        return ApiResponse::ok(null, 'Permisos directos actualizados.');
    }

    private function detectarModulo(string $permiso): string
    {
        return match(true) {
            // Las tres excepciones van primero porque las ramas genéricas de
            // abajo las capturaban por subcadena: 'rol' se comía
            // 'ver-rol-pago-propio', 'contrasena' se comía 'cambiar-contrasena'
            // y 'bien' se comía 'gestionar-bienestar'.
            str_contains($permiso, 'rol-pago')          => 'Nómina',
            $permiso === 'cambiar-contrasena'           => 'Autoservicio',
            str_contains($permiso, 'bienestar')         => 'Actividades y Bienestar',

            str_contains($permiso, 'usuario') ||
            str_contains($permiso, 'rol') ||
            str_contains($permiso, 'contrasena') ||
            str_contains($permiso, 'auditoria') ||
            str_contains($permiso, 'sistema') ||
            str_contains($permiso, 'configurar')
                => 'Sistema',

            str_contains($permiso, 'estructura') ||
            str_contains($permiso, 'puesto') ||
            str_contains($permiso, 'organigrama') ||
            str_contains($permiso, 'distributivo')
                => 'Estructura',

            str_contains($permiso, 'expediente') ||
            str_contains($permiso, 'documento') ||
            str_contains($permiso, 'retencion') ||
            str_contains($permiso, 'firmar') ||
            str_contains($permiso, 'cargas')
                => 'Expediente',

            str_contains($permiso, 'nomina') ||
            str_contains($permiso, 'handoff') ||
            str_contains($permiso, 'rol-pago')
                => 'Nómina',

            str_contains($permiso, 'asistencia') ||
            str_contains($permiso, 'permiso') ||
            str_contains($permiso, 'vacacion')
                => 'Asistencia',

            str_contains($permiso, 'viatico') ||
            str_contains($permiso, 'liquidar') ||
            str_contains($permiso, 'aprobar-viatico')
                => 'Viáticos',

            str_contains($permiso, 'dispensario') ||
            str_contains($permiso, 'historia') ||
            str_contains($permiso, 'consulta') ||
            str_contains($permiso, 'receta') ||
            str_contains($permiso, 'medicamento') ||
            str_contains($permiso, 'agenda') ||
            str_contains($permiso, 'cita') ||
            str_contains($permiso, 'sso') ||
            str_contains($permiso, 'accidente') ||
            str_contains($permiso, 'fichas')
                => 'Dispensario y SSO',

            str_contains($permiso, 'evaluacion') ||
            str_contains($permiso, 'desempeno')
                => 'Evaluación',

            str_contains($permiso, 'capacitacion') ||
            str_contains($permiso, 'curso') ||
            str_contains($permiso, 'inscribirse')
                => 'Capacitación',

            str_contains($permiso, 'ticket') ||
            str_contains($permiso, 'inventario') ||
            str_contains($permiso, 'tecnico') ||
            str_contains($permiso, 'sla') ||
            str_contains($permiso, 'bien') ||
            str_contains($permiso, 'base-conocimiento')
                => 'TI y Helpdesk',

            str_contains($permiso, 'reporte') ||
            str_contains($permiso, 'exportar') ||
            str_contains($permiso, 'dashboard')
                => 'Reportería',

            str_contains($permiso, 'actividad') ||
            str_contains($permiso, 'bienestar') ||
            str_contains($permiso, 'clima') ||
            str_contains($permiso, 'encuesta')
                => 'Actividades y Bienestar',

            str_contains($permiso, 'sumario') ||
            str_contains($permiso, 'sancion') ||
            str_contains($permiso, 'recepcion') ||
            str_contains($permiso, 'trabajo-social')
                => 'Disciplinario',

            str_contains($permiso, 'convocatoria') ||
            str_contains($permiso, 'postulante') ||
            str_contains($permiso, 'onboarding')
                => 'Selección',

            str_contains($permiso, 'autoservicio') ||
            str_contains($permiso, 'cambiar-contrasena')
                => 'Autoservicio',

            default => 'General',
        };
    }
}
