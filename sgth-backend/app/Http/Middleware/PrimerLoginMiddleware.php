<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class PrimerLoginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->primer_login) {
            // Permitir el acceso exclusivo al endpoint de cambiar contraseña y logout
            if ($request->is('api/v1/auth/cambiar-contrasena') || $request->is('api/v1/auth/logout')) {
                return $next($request);
            }

            return ApiResponse::error(
                'Por seguridad, debe cambiar su contraseña inicial (número de cédula) antes de continuar.',
                null,
                403
            );
        }

        return $next($request);
    }
}
