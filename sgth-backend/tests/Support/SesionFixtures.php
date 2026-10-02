<?php

use App\Support\CookieDeSesion;
use Illuminate\Testing\TestResponse;

/**
 * El token que el login dejó en la cookie HttpOnly.
 *
 * Ya no viene en el cuerpo de la respuesta; las pruebas que necesitan
 * reutilizarlo como Bearer lo sacan de aquí.
 */
function tokenDelLogin(TestResponse $respuesta): ?string
{
    return $respuesta->getCookie(CookieDeSesion::TOKEN, decrypt: false)?->getValue();
}
