<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta el acceso de un usuario desactivado que todavía conserva un token.
 *
 * Desactivar revoca los tokens en el momento, pero `activo` también cambia por
 * otras vías (desvincular servidor, un UPDATE manual en la base). Sin esta
 * comprobación un token emitido antes del cambio seguiría siendo válido hasta
 * su expiración.
 */
class UsuarioActivoMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->activo) {
            $user->currentAccessToken()?->delete();

            return ApiResponse::error(
                'Su cuenta está desactivada. Comuníquese con la Dirección de TI.',
                null,
                403,
            );
        }

        return $next($request);
    }
}
