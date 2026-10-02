<?php

namespace App\Http\Middleware;

use App\Support\CookieDeSesion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte la cookie `HttpOnly` del token en el `Authorization: Bearer` de
 * siempre, para que Sanctum, las pruebas y cualquier otro cliente sigan
 * funcionando igual.
 *
 * Una cookie viaja sola, y eso abre la puerta a peticiones falsificadas desde
 * otro sitio (CSRF). Por eso solo se usa si la petición trae
 * `X-Requested-With: XMLHttpRequest`: un formulario ajeno no puede ponerla, y
 * un `fetch` desde otro origen con esa cabecera exige un permiso CORS que no
 * se le da. Sin ella la petición sigue, pero como anónima.
 *
 * Si ya llega un Bearer, manda el Bearer.
 */
final class TokenDesdeCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookies->get(CookieDeSesion::TOKEN);

        if (
            is_string($token)
            && $token !== ''
            && $request->bearerToken() === null
            && $request->header('X-Requested-With') === 'XMLHttpRequest'
        ) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
