<?php

use App\Models\User;
use App\Support\CookieDeSesion;
use App\Support\ProxiesDeConfianza;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Detrás de un proxy, Laravel veía la IP del proxy en todas las peticiones:
 * el límite de fallos de login por IP lo compartía la institución entera y
 * la cookie del token no sabía que la conexión era HTTPS. Solo se cree
 * X-Forwarded-For a los proxies de TRUSTED_PROXIES.
 */

const IP_DEL_PROXY = '10.0.0.5';

beforeEach(function () {
    config(['session.secure' => null]);

    User::factory()->create([
        'usuario_ti'   => 'rquinonez',
        'password'     => Hash::make('ClaveSegura123'),
        'primer_login' => false,
    ]);
});

// Es estado estático del middleware: sin limpiarlo pasaría a la prueba siguiente.
afterEach(fn () => TrustProxies::flushState());

function loginDesde(string $remoto, array $cabeceras, string $contrasena): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $remoto])
        ->withHeaders($cabeceras)
        ->postJson('/api/v1/auth/login', ['usuario' => 'rquinonez', 'contrasena' => $contrasena]);
}

function cincoFallosDe(string $remoto, string $ipReal): void
{
    foreach (range(1, 5) as $intento) {
        loginDesde($remoto, ['X-Forwarded-For' => $ipReal], 'equivocada1')->assertUnauthorized();
    }
}

test('sin proxies configurados, X-Forwarded-For no cambia la IP', function () {
    ProxiesDeConfianza::aplicar(null);

    cincoFallosDe(IP_DEL_PROXY, '200.1.1.1');

    // Otra «persona» detrás del mismo proxy: comparte el bloqueo.
    loginDesde(IP_DEL_PROXY, ['X-Forwarded-For' => '200.1.1.2'], 'ClaveSegura123')
        ->assertStatus(429);
});

test('con el proxy de confianza, cada persona tiene su propia IP', function () {
    ProxiesDeConfianza::aplicar(IP_DEL_PROXY);

    cincoFallosDe(IP_DEL_PROXY, '200.1.1.1');

    loginDesde(IP_DEL_PROXY, ['X-Forwarded-For' => '200.1.1.2'], 'ClaveSegura123')->assertOk();
    loginDesde(IP_DEL_PROXY, ['X-Forwarded-For' => '200.1.1.1'], 'ClaveSegura123')->assertStatus(429);
});

test('quien no es el proxy no puede inventarse la IP', function () {
    ProxiesDeConfianza::aplicar(IP_DEL_PROXY);

    cincoFallosDe('192.168.1.50', '200.1.1.1');

    loginDesde('192.168.1.50', ['X-Forwarded-For' => '200.9.9.9'], 'ClaveSegura123')
        ->assertStatus(429);
});

test('admite varios proxies y rangos CIDR separados por comas', function () {
    ProxiesDeConfianza::aplicar(' 172.28.0.0/16 , 10.0.0.0/24 ');

    cincoFallosDe(IP_DEL_PROXY, '200.1.1.1');

    loginDesde('172.28.4.2', ['X-Forwarded-For' => '200.1.1.2'], 'ClaveSegura123')->assertOk();
});

test('el comodín se ignora y se avisa en el registro', function () {
    Log::spy();

    ProxiesDeConfianza::aplicar('*');

    Log::shouldHaveReceived('warning')->once();

    cincoFallosDe(IP_DEL_PROXY, '200.1.1.1');

    loginDesde(IP_DEL_PROXY, ['X-Forwarded-For' => '200.1.1.2'], 'ClaveSegura123')
        ->assertStatus(429);
});

test('con HTTPS terminado en el proxy, la cookie del token sale Secure', function () {
    ProxiesDeConfianza::aplicar(IP_DEL_PROXY);

    $cookie = loginDesde(IP_DEL_PROXY, ['X-Forwarded-Proto' => 'https'], 'ClaveSegura123')
        ->assertOk()
        ->getCookie(CookieDeSesion::TOKEN, decrypt: false);

    expect($cookie->isSecure())->toBeTrue();
});

test('sin confiar en el proxy, un X-Forwarded-Proto no vuelve Secure la cookie', function () {
    ProxiesDeConfianza::aplicar(null);

    $cookie = loginDesde(IP_DEL_PROXY, ['X-Forwarded-Proto' => 'https'], 'ClaveSegura123')
        ->assertOk()
        ->getCookie(CookieDeSesion::TOKEN, decrypt: false);

    expect($cookie->isSecure())->toBeFalse();
});
