<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class VerificarRol
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('No autenticado. Por favor inicie sesión.', null, 401);
        }

        if (!$user->hasAnyRole($roles)) {
            return ApiResponse::noAutorizado('No tiene el rol necesario para realizar esta acción.');
        }

        return $next($request);
    }
}
