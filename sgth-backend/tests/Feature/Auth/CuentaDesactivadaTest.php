<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * `activo` era decorativo: el login no lo miraba y ningún middleware lo
 * comprobaba, así que desactivar a un usuario desde la tabla no le impedía
 * seguir entrando y trabajando.
 */
beforeEach(function () {
    User::unguard();

    $this->usuario = User::create([
        'email'        => 'desactivado@example.com',
        'usuario_ti'   => 'desactivado',
        'password'     => bcrypt('secreta123'),
        'primer_login' => false,
        'activo'       => false,
    ]);

    $this->vigente = User::create([
        'email'        => 'vigente@example.com',
        'usuario_ti'   => 'vigente',
        'password'     => bcrypt('secreta123'),
        'primer_login' => false,
        'activo'       => true,
    ]);
});

test('un usuario desactivado no puede iniciar sesión', function () {
    $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'desactivado',
        'contrasena' => 'secreta123',
    ])->assertStatus(403);
});

test('un usuario activo sí puede iniciar sesión', function () {
    $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'vigente',
        'contrasena' => 'secreta123',
    ])
        ->assertOk()
        ->assertJsonStructure(['datos' => ['token']]);
});

test('la contraseña se valida antes que el estado: una clave mala no revela la cuenta', function () {
    $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'desactivado',
        'contrasena' => 'incorrecta',
    ])->assertStatus(401);
});

test('el token de un usuario activo da acceso', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'vigente',
        'contrasena' => 'secreta123',
    ])->json('datos.token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/perfil')
        ->assertOk();
});

test('un token emitido antes de la desactivación deja de servir', function () {
    $token = $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'vigente',
        'contrasena' => 'secreta123',
    ])->json('datos.token');

    // Desactivación por una vía que no revoca tokens (p. ej. un UPDATE directo
    // en base, o desvincular el servidor).
    $this->vigente->update(['activo' => false]);

    $this->withToken($token)
        ->getJson('/api/v1/auth/perfil')
        ->assertStatus(403);
});
