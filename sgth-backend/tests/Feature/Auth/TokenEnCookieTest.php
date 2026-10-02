<?php

use App\Models\User;
use App\Support\CookieDeSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El token viajaba en el cuerpo del login y el frontend lo guardaba donde el
 * JavaScript de la página podía leerlo: un XSS se llevaba la cuenta. Ahora va
 * en una cookie HttpOnly que solo el servidor lee.
 */

beforeEach(function () {
    $this->usuario = User::factory()->create([
        'usuario_ti'   => 'kmontano',
        'password'     => Hash::make('ClaveSegura123'),
        'primer_login' => false,
    ]);
});

function loginConCookie(): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'usuario'    => 'kmontano',
        'contrasena' => 'ClaveSegura123',
    ]);
}

/** Una petición como la del navegador: solo la cookie, sin Bearer. */
function perfilConCookie(string $token, bool $conCabeceraAjax = true): TestResponse
{
    app('auth')->forgetGuards();

    $cabeceras = $conCabeceraAjax ? ['X-Requested-With' => 'XMLHttpRequest'] : [];

    // `withCredentials`: las peticiones JSON de las pruebas no mandan cookies
    // si no se pide, y el navegador sí las manda al mismo origen.
    return test()
        ->withCredentials()
        ->withUnencryptedCookie(CookieDeSesion::TOKEN, $token)
        ->getJson('/api/v1/auth/perfil', $cabeceras);
}

test('el login no devuelve el token en el cuerpo', function () {
    $respuesta = loginConCookie()->assertOk();

    expect($respuesta->json('datos'))->not->toHaveKey('token');
});

test('el token va en una cookie HttpOnly, Lax y con la caducidad del token', function () {
    $cookie = loginConCookie()->getCookie(CookieDeSesion::TOKEN, decrypt: false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getExpiresTime())
        ->toBeGreaterThan(now()->addMinutes(config('sanctum.expiration') - 1)->getTimestamp());

    // Es el token de verdad, no una copia que haya que traducir.
    expect($this->usuario->tokens()->count())->toBe(1);
});

test('la cookie de sesión es legible y no lleva el token', function () {
    $cookie = loginConCookie()->getCookie(CookieDeSesion::SESION, decrypt: false);

    expect($cookie->isHttpOnly())->toBeFalse()
        ->and($cookie->getValue())->toBe('1');
});

test('con la cookie y la cabecera AJAX se entra', function () {
    $token = tokenDelLogin(loginConCookie());

    perfilConCookie($token)
        ->assertOk()
        ->assertJsonPath('datos.usuario_ti', 'kmontano');
});

test('sin la cabecera AJAX la cookie no autentica, contra peticiones de otro sitio', function () {
    $token = tokenDelLogin(loginConCookie());

    perfilConCookie($token, conCabeceraAjax: false)->assertUnauthorized();
});

test('un Bearer explícito sigue funcionando y manda sobre la cookie', function () {
    $token = tokenDelLogin(loginConCookie());

    app('auth')->forgetGuards();

    $this->withCredentials()
        ->withUnencryptedCookie(CookieDeSesion::TOKEN, 'basura')
        ->withToken($token)
        ->getJson('/api/v1/auth/perfil', ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();
});

test('cerrar sesión borra las dos cookies y revoca el token', function () {
    $token = tokenDelLogin(loginConCookie());

    app('auth')->forgetGuards();

    $salida = $this->withCredentials()
        ->withUnencryptedCookie(CookieDeSesion::TOKEN, $token)
        ->postJson('/api/v1/auth/logout', [], ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk();

    foreach ([CookieDeSesion::TOKEN, CookieDeSesion::SESION] as $nombre) {
        $cookie = $salida->getCookie($nombre, decrypt: false);

        expect($cookie)->not->toBeNull()
            ->and($cookie->getValue())->toBe('')
            ->and($cookie->getExpiresTime())->toBeLessThan(time());
    }

    perfilConCookie($token)->assertUnauthorized();
});

test('un login fallido no deja cookie', function () {
    $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'kmontano',
        'contrasena' => 'equivocada1',
    ])
        ->assertUnauthorized()
        ->assertCookieMissing(CookieDeSesion::TOKEN);
});
