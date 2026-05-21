<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Http\Responses\ApiResponse;
use App\Contracts\Admin\UsuarioServiceInterface;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class UsuarioController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UsuarioServiceInterface $usuarioService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $usuarios = $this->usuarioService->listar($request->all());
        return ApiResponse::paginado($usuarios);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarioService->crear($request->validated());
        return ApiResponse::created($usuario, 'Usuario creado exitosamente.');
    }

    public function show(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('view', $usuario);

        return ApiResponse::ok($usuario);
    }

    public function update(UpdateUsuarioRequest $request, int $id): JsonResponse
    {
        $usuario = $this->usuarioService->actualizar($id, $request->validated());
        return ApiResponse::ok($usuario, 'Usuario actualizado exitosamente.');
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
        return ApiResponse::ok(null, 'Contraseña restablecida al número de cédula exitosamente.');
    }

    public function toggleActivo(int $id): JsonResponse
    {
        $usuario = $this->usuarioService->obtener($id);
        $this->authorize('toggleActivo', $usuario);
        $usuario->update(['activo' => !$usuario->activo]);
        $estado = $usuario->activo ? 'activado' : 'desactivado';
        return ApiResponse::ok($usuario, "Usuario {$estado} exitosamente.");
    }

    public function sinServidor(): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $usuarios = User::whereDoesntHave('servidor')
            ->where('activo', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
        return ApiResponse::ok($usuarios, 'Usuarios sin servidor asignado.');
    }
}
