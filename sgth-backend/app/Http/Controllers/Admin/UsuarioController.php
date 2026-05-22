<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Http\Resources\Admin\UsuarioResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Admin\UsuarioServiceInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;

final class UsuarioController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UsuarioServiceInterface $usuarioService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $paginator = $this->usuarioService->listar($request->all());

        return response()->json([
            'exito'   => true,
            'mensaje' => 'Consulta exitosa.',
            'datos'   => UsuarioResource::collection($paginator->items()),
            'meta'    => [
                'pagina_actual' => $paginator->currentPage(),
                'por_pagina'    => $paginator->perPage(),
                'total'         => $paginator->total(),
                'ultima_pagina' => $paginator->lastPage(),
                'desde'         => $paginator->firstItem(),
                'hasta'         => $paginator->lastItem(),
            ],
        ]);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->crear($request->validated());
        return ApiResponse::created(
            new UsuarioResource($usuario->load('roles')),
            'Usuario creado exitosamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('view', $usuario);
        return ApiResponse::ok(
            new UsuarioResource($usuario->load(['roles', 'servidor']))
        );
    }

    public function update(UpdateUsuarioRequest $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->actualizar($id, $request->validated());
        return ApiResponse::ok(
            new UsuarioResource($usuario->load('roles')),
            'Usuario actualizado exitosamente.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('delete', $usuario);
        $this->usuarioService->eliminar($id);
        return ApiResponse::noContent('Usuario eliminado exitosamente.');
    }

    public function restablecerContrasena(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('restablecerContrasena', $usuario);
        $this->usuarioService->restablecerContrasena($id);
        return ApiResponse::ok(null, 'Contraseña restablecida exitosamente.');
    }

    public function toggleActivo(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('toggleActivo', $usuario);
        $usuario->update(['activo' => !$usuario->activo]);
        $estado = $usuario->fresh()->activo ? 'activado' : 'desactivado';
        return ApiResponse::ok(
            new UsuarioResource($usuario->fresh('roles')),
            "Usuario {$estado} exitosamente."
        );
    }

    public function sinServidor(): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $usuarios = User::whereDoesntHave('servidor')
            ->where('activo', true)
            ->with('roles')
            ->orderBy('name')
            ->get();
        return ApiResponse::ok(
            UsuarioResource::collection($usuarios),
            'Usuarios disponibles para asignar a servidor.'
        );
    }

    /**
     * Lista todos los roles disponibles del sistema.
     * Usado por el frontend para el Select de roles.
     */
    public function roles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $roles = Role::orderBy('name')->pluck('name');
        return ApiResponse::ok($roles, 'Roles del sistema.');
    }
}
