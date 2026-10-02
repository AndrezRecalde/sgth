<?php

use App\Models\Dispensario\DisponibilidadMedico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El menú de usuario cerraba la sesión solo en el navegador: nunca llamaba a
 * `auth/logout`. El token seguía valiendo hasta caducar y el médico que salía
 * quedaba disponible en el Dispensario. Esto fija lo que esa llamada hace.
 */

function tokenDeSesionParaCerrar(User $usuario): string
{
    return test()->postJson('/api/v1/auth/login', [
        'usuario'    => $usuario->usuario_ti,
        'contrasena' => 'ClaveSegura123',
    ])->json('datos.token');
}

beforeEach(function () {
    $this->usuario = User::factory()->create([
        'usuario_ti'   => 'medico-que-sale',
        'password'     => Hash::make('ClaveSegura123'),
        'primer_login' => false,
    ]);
});

test('cerrar sesión revoca el token con el que se cerró', function () {
    $token = tokenDeSesionParaCerrar($this->usuario);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($this->usuario->tokens()->count())->toBe(0);

    // El guard recuerda al usuario de la petición anterior; sin olvidarlo, la
    // siguiente pasaría aunque el token ya no exista.
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/perfil')->assertUnauthorized();
});

test('cerrar sesión deja al médico como no disponible', function () {
    DisponibilidadMedico::create([
        'user_id'        => $this->usuario->id,
        'disponible'     => true,
        'actualizado_en' => now(),
    ]);

    $token = tokenDeSesionParaCerrar($this->usuario);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect(DisponibilidadMedico::where('user_id', $this->usuario->id)->value('disponible'))
        ->toBeFalse();
});

test('cerrar sesión en un dispositivo no cierra las demás', function () {
    $estaSesion = tokenDeSesionParaCerrar($this->usuario);
    tokenDeSesionParaCerrar($this->usuario);

    $this->withToken($estaSesion)->postJson('/api/v1/auth/logout')->assertOk();

    expect($this->usuario->tokens()->count())->toBe(1);
});
