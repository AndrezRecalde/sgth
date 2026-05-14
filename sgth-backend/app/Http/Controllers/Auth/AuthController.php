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
        return ApiResponse::ok($request->user()->load('roles'));
    }
}
