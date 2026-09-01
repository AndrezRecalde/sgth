<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Admin\UsuarioServiceInterface;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AsignarServidorRequest;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\SugerirUsuarioTiRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Http\Resources\Admin\UsuarioResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final class UsuarioController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UsuarioServiceInterface $usuarioService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $paginator = $this->usuarioService->listar($request->all());

        return response()->json([
            'exito' => true,
            'mensaje' => 'Consulta exitosa.',
            'datos' => UsuarioResource::collection($paginator->items()),
            'meta' => [
                'pagina_actual' => $paginator->currentPage(),
                'por_pagina' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ultima_pagina' => $paginator->lastPage(),
                'desde' => $paginator->firstItem(),
                'hasta' => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->crear($request->validated());

        return ApiResponse::created(
            new UsuarioResource($usuario),
            'Usuario creado exitosamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('view', $usuario);

        return ApiResponse::ok(new UsuarioResource($usuario));
    }

    public function update(UpdateUsuarioRequest $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->actualizar(
            $id,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::ok(
            new UsuarioResource($usuario),
            'Usuario actualizado exitosamente.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('delete', $usuario);

        $this->usuarioService->eliminar($id, $request->user());

        return ApiResponse::noContent('Usuario eliminado exitosamente.');
    }

    public function restablecerContrasena(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('restablecerContrasena', $usuario);

        $this->usuarioService->restablecerContrasena($id);

        return ApiResponse::ok(null, 'Contraseña restablecida exitosamente.');
    }

    public function toggleActivo(Request $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('toggleActivo', $usuario);

        $usuario = $this->usuarioService->alternarActivo($id, $request->user());
        $estado = $usuario->activo ? 'activado' : 'desactivado';

        return ApiResponse::ok(
            new UsuarioResource($usuario),
            "Usuario {$estado} exitosamente."
        );
    }

    public function desvincularServidor(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('vincularServidor', $usuario);

        return ApiResponse::ok(
            new UsuarioResource(
                $this->usuarioService->desvincularServidor($id)
            ),
            'Servidor desvinculado del usuario correctamente.'
        );
    }

    public function asignarServidor(AsignarServidorRequest $request, int $id): JsonResponse
    {
        return ApiResponse::ok(
            new UsuarioResource(
                $this->usuarioService->asignarServidor(
                    $id,
                    $request->validated('servidor_id'),
                )
            ),
            'Servidor asignado al usuario correctamente.'
        );
    }

    public function sugerirUsuarioTi(SugerirUsuarioTiRequest $request): JsonResponse
    {
        return ApiResponse::ok(
            [
                'usuario_ti_sugerido' => $this->usuarioService->sugerirUsuarioTi(
                    $request->validated('servidor_id'),
                    $request->validated('nombre'),
                    $request->validated('apellido'),
                ),
            ],
            'Usuario TI sugerido generado.'
        );
    }

    /**
     * Lista los roles del sistema con su etiqueta legible.
     * Alimenta el selector de roles y el filtro del listado en el frontend.
     */
    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = Role::orderBy('name')
            ->pluck('name')
            ->map(fn(string $nombre) => [
                'valor'     => $nombre,
                'etiqueta'  => Rol::tryFrom($nombre)?->etiqueta() ?? $nombre,
            ])
            ->values();

        return ApiResponse::ok($roles, 'Roles del sistema.');
    }
}
