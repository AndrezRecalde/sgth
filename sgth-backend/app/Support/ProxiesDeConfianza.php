<?php

namespace App\Support;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Log;

/**
 * Los proxies delante de Laravel, a partir de `app.trusted_proxies`.
 *
 * Solo a ellos se les cree X-Forwarded-For. Sin la variable no se confía en
 * nadie, que es lo que pasaba hasta ahora.
 */
final class ProxiesDeConfianza
{
    public static function aplicar(?string $configurados): void
    {
        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $configurados),
        )));

        if ($proxies === []) {
            return;
        }

        // `*` dejaría a cualquiera que llegue directo inventarse la IP y
        // esquivar el límite de intentos del login. Se ignora y se avisa.
        if (in_array('*', $proxies, true) || in_array('**', $proxies, true)) {
            Log::warning('TRUSTED_PROXIES no admite comodines: se ignora y no se confía en ningún proxy.');

            return;
        }

        TrustProxies::at($proxies);
    }
}
