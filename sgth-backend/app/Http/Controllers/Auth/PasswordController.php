<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActualizarContrasenaRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Cambio de contraseña voluntario.
 *
 * El cambio obligatorio del primer acceso sigue en AuthController, porque
 * forma parte del inicio de sesión. Este es el que la persona elige hacer
 * desde su menú, y por eso pide la contraseña actual.
 */
final class PasswordController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    public function actualizar(ActualizarContrasenaRequest $request): JsonResponse
    {
        $this->authService->cambiarContrasena(
            $request->user(),
            $request->validated('nueva_contrasena'),
        );

        return ApiResponse::ok(
            null,
            'Contraseña actualizada. Se cerró la sesión en los demás equipos.',
        );
    }
}
