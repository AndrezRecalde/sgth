<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\Auth\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\CambiarContrasenaRequest;
use App\Http\Resources\Auth\UsuarioAutenticadoResource;
use App\Http\Responses\ApiResponse;
use App\Support\CookieDeSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Contracts\Dispensario\DisponibilidadServiceInterface;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly DisponibilidadServiceInterface $disponibilidadService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->authService->login(
            $request->validated('usuario'),
            $request->validated('contrasena'),
            $request->ip(),
        );

        // El token no va en el cuerpo: lo que el JavaScript de la página
        // puede leer, un XSS también. Viaja en una cookie HttpOnly.
        [$cookieToken, $cookieSesion] = CookieDeSesion::crear($request, $resultado['token']);

        // El servicio entrega el modelo; lo que viaja es la misma forma del
        // perfil, con roles y permisos, que el frontend guarda tal cual. Va el
        // recurso sin resolver, como en el resto de controladores: se serializa
        // igual, y así Scramble lo documenta con sus campos en el contrato.
        return ApiResponse::ok([
            'primer_login' => $resultado['primer_login'],
            'usuario'      => new UsuarioAutenticadoResource($resultado['usuario']),
        ], 'Inicio de sesión exitoso.')
            ->withCookie($cookieToken)
            ->withCookie($cookieSesion);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $this->disponibilidadService->marcarNoDisponible($userId);

        $request->user()->currentAccessToken()->delete();

        [$cookieToken, $cookieSesion] = CookieDeSesion::olvidar($request);

        return ApiResponse::noContent('Sesión cerrada exitosamente.')
            ->withCookie($cookieToken)
            ->withCookie($cookieSesion);
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
        return ApiResponse::ok(new UsuarioAutenticadoResource($request->user()));
    }
}
