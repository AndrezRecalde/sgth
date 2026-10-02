<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Las dos cookies con las que el navegador lleva la sesión.
 *
 * El token de Sanctum viajaba en el cuerpo del login y el frontend lo guardaba
 * en `localStorage` y en una cookie que el JavaScript podía leer: cualquier
 * XSS se lo llevaba, y con él la cuenta durante 24 horas. Ahora lo escribe
 * Laravel en una cookie `HttpOnly`, que el código de la página no ve.
 *
 * - `sgth_token` — el token, `HttpOnly`. Lo usan `TokenDesdeCookie` (API) y
 *   `proxy.ts` (qué pantallas se pueden abrir), los dos en el servidor.
 * - `sgth_sesion` — solo un `1`, legible. El store del frontend lo mira para
 *   saber si la sesión que guardó sigue viva sin tener acceso al token.
 *
 * Las dos caducan con el token (`sanctum.expiration`), para que no haya dos
 * relojes. `SameSite=Lax` y no `Strict`: el QR del permiso y los enlaces de
 * correo abren el sistema desde fuera, y con `Strict` el proxy no vería la
 * sesión en esa primera navegación. Lax ya impide que otro sitio dispare un
 * POST con ellas; `TokenDesdeCookie` exige además una cabecera que un
 * formulario ajeno no puede poner.
 */
final class CookieDeSesion
{
    public const TOKEN  = 'sgth_token';
    public const SESION = 'sgth_sesion';

    /**
     * @return array{0: Cookie, 1: Cookie}
     */
    public static function crear(Request $request, string $token): array
    {
        $minutos = (int) config('sanctum.expiration', 1440);

        return [
            self::cookie($request, self::TOKEN, $token, $minutos, httpOnly: true),
            self::cookie($request, self::SESION, '1', $minutos, httpOnly: false),
        ];
    }

    /**
     * @return array{0: Cookie, 1: Cookie}
     */
    public static function olvidar(Request $request): array
    {
        return [
            self::cookie($request, self::TOKEN, '', -2628000, httpOnly: true),
            self::cookie($request, self::SESION, '', -2628000, httpOnly: false),
        ];
    }

    private static function cookie(
        Request $request,
        string $nombre,
        string $valor,
        int $minutos,
        bool $httpOnly,
    ): Cookie {
        // Sin HTTPS una cookie `Secure` no se guarda: en desarrollo se decide
        // por la petición, y en producción manda SESSION_SECURE_COOKIE si se
        // fijó (detrás de un nginx que termina TLS, la petición llega en http).
        $segura = config('session.secure') ?? $request->isSecure();

        return Cookie::create(
            name: $nombre,
            value: $valor,
            expire: time() + $minutos * 60,
            path: '/',
            domain: null,
            secure: (bool) $segura,
            httpOnly: $httpOnly,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }
}
