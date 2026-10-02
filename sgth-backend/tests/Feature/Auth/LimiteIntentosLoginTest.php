<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El login se limitaba con `throttle:5,1` por IP contando también los
 * aciertos: detrás de la NAT de la institución, cinco personas entrando en el
 * mismo minuto dejaban fuera a la sexta. Ahora cuentan solo los fallos, por
 * cuenta y por IP.
 */

function intentarLogin(string $usuario, string $contrasena, string $ip = '10.0.0.1'): TestResponse
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', ['usuario' => $usuario, 'contrasena' => $contrasena]);
}

beforeEach(function () {
    $this->usuario = User::factory()->create([
        'usuario_ti'   => 'jcortez',
        'password'     => Hash::make('ClaveSegura123'),
        'primer_login' => false,
    ]);
});

test('muchas personas pueden entrar desde la misma IP en el mismo minuto', function () {
    foreach (range(1, 8) as $n) {
        User::factory()->create([
            'usuario_ti' => "persona{$n}",
            'password'   => Hash::make('ClaveSegura123'),
        ]);

        intentarLogin("persona{$n}", 'ClaveSegura123')->assertOk();
    }
});

test('cinco fallos bloquean esa cuenta desde ese equipo', function () {
    foreach (range(1, 5) as $intento) {
        intentarLogin('jcortez', 'equivocada1')->assertUnauthorized();
    }

    // Ni siquiera la clave correcta entra mientras dura el bloqueo.
    intentarLogin('jcortez', 'ClaveSegura123')
        ->assertStatus(429)
        ->assertJsonPath('exito', false);
});

test('el bloqueo no distingue mayúsculas en el usuario', function () {
    foreach (['jcortez', 'JCortez', 'JCORTEZ', ' jcortez', 'Jcortez'] as $variante) {
        intentarLogin($variante, 'equivocada1');
    }

    intentarLogin('jcortez', 'ClaveSegura123')->assertStatus(429);
});

test('el bloqueo de una cuenta no afecta a otro equipo ni a otra cuenta', function () {
    foreach (range(1, 5) as $intento) {
        intentarLogin('jcortez', 'equivocada1');
    }

    intentarLogin('jcortez', 'ClaveSegura123', '10.0.0.2')->assertOk();

    User::factory()->create(['usuario_ti' => 'otra', 'password' => Hash::make('ClaveSegura123')]);
    intentarLogin('otra', 'ClaveSegura123')->assertOk();
});

test('acertar la clave reinicia los fallos de la cuenta', function () {
    foreach (range(1, 4) as $intento) {
        intentarLogin('jcortez', 'equivocada1');
    }

    intentarLogin('jcortez', 'ClaveSegura123')->assertOk();

    foreach (range(1, 4) as $intento) {
        intentarLogin('jcortez', 'equivocada1')->assertUnauthorized();
    }

    intentarLogin('jcortez', 'ClaveSegura123')->assertOk();
});

test('probar una clave contra muchas cuentas desde una IP acaba bloqueado', function () {
    foreach (range(1, 30) as $n) {
        intentarLogin("inexistente{$n}", 'Clave2026')->assertUnauthorized();
    }

    intentarLogin('jcortez', 'ClaveSegura123')->assertStatus(429);
    intentarLogin('jcortez', 'ClaveSegura123', '10.0.0.9')->assertOk();
});

test('un usuario inexistente también pasa por la comparación de la contraseña', function () {
    // Sin esa comparación la respuesta llegaba al instante, y el tiempo
    // delataba qué usuarios existen.
    Hash::spy();

    intentarLogin('nadie-se-llama-asi', 'Clave2026')->assertUnauthorized();

    Hash::shouldHaveReceived('check')->once();
});
