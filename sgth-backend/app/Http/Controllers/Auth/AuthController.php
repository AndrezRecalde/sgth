<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\CambiarContrasenaRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->authService->login(
            $request->validated('usuario'),
            $request->validated('contrasena'),
        );

        return ApiResponse::ok($resultado, 'Inicio de sesión exitoso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::noContent('Sesión cerrada exitosamente.');
    }

    public function cambiarContrasenaInicial(CambiarContrasenaRequest $request): JsonResponse
    {
        $this->authService->cambiarContrasenaInicial(
            $request->user(),
            $request->validated('nueva_contrasena'),
        );

        return ApiResponse::ok(null, 'Contraseña actualizada exitosamente.');
    }

    public function perfil(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'roles',
            'servidor.puesto.cargo',
            'servidor.unidadAdministrativa',
        ]);

        $roles    = $user->roles->pluck('name')->toArray();
        $permisos = $user->getAllPermissions()
                         ->pluck('name')
                         ->toArray();

        return ApiResponse::ok([
            'id'              => $user->id,
            'nombre_completo' => $user->nombre_completo,
            'email'           => $user->email,
            'usuario_ti'      => $user->usuario_ti,
            'activo'          => $user->activo,
            'primer_login'    => $user->primer_login,
            'servidor_id'     => $user->servidor_id,
            'roles'           => $roles,
            'permisos'        => $permisos,
            'servidor'        => $user->servidor ? [
                'id'      => $user->servidor->id,
                'cedula'  => $user->servidor->cedula,
                'nombre'  => $user->servidor->nombre,
                'apellido'=> $user->servidor->apellido,
                'puesto'  => $user->servidor->puesto ? [
                    'nombre' => $user->servidor
                                    ->puesto->cargo?->nombre,
                ] : null,
                'unidad_administrativa' =>
                    $user->servidor->unidadAdministrativa
                    ? [
                        'nombre' => $user->servidor
                            ->unidadAdministrativa->nombre,
                    ] : null,
            ] : null,
        ]);
    }
}
