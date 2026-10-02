<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * `cambiar-contrasena` no pide la contraseña actual. Estaba abierta a
 * cualquier sesión, y aceptaba como «nueva» la misma clave inicial.
 */

const CEDULA_PRIMER_ACCESO = '0801234567';

function usuarioEnPrimerAcceso(array $atributos = []): User
{
    $unidad = unidadDePrueba(['nombre' => 'Dirección de TI']);

    $servidor = Servidor::create([
        'cedula'                   => CEDULA_PRIMER_ACCESO,
        'nombre'                   => 'Ana',
        'apellido'                 => 'Quiñónez',
        'puesto_id'                => puestoDePrueba($unidad, 'Analista de TI')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    return User::factory()->create(array_merge([
        'password'     => Hash::make(CEDULA_PRIMER_ACCESO),
        'primer_login' => true,
        'servidor_id'  => $servidor->id,
    ], $atributos));
}

test('quien ya cambió su contraseña inicial no puede usar esta ruta', function () {
    $usuario = usuarioEnPrimerAcceso(['primer_login' => false]);

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', ['nueva_contrasena' => 'OtraClave123'])
        ->assertForbidden();

    expect(Hash::check(CEDULA_PRIMER_ACCESO, $usuario->fresh()->password))->toBeTrue();
});

test('la nueva contraseña no puede contener la cédula', function () {
    $usuario = usuarioEnPrimerAcceso();

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', ['nueva_contrasena' => 'a' . CEDULA_PRIMER_ACCESO])
        ->assertStatus(422)
        ->assertJsonPath('errores.nueva_contrasena.0', 'La nueva contraseña no puede contener su número de cédula.');

    expect($usuario->fresh()->primer_login)->toBeTrue();
});

test('la nueva contraseña no puede ser la actual', function () {
    // TI puede haber fijado a mano una clave inicial que no es la cédula.
    $usuario = usuarioEnPrimerAcceso(['password' => Hash::make('Inicial2026')]);

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', ['nueva_contrasena' => 'Inicial2026'])
        ->assertStatus(422)
        ->assertJsonPath('errores.nueva_contrasena.0', 'La nueva contraseña debe ser distinta de la actual.');
});

test('una contraseña nueva válida completa el primer acceso', function () {
    $usuario = usuarioEnPrimerAcceso();

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', ['nueva_contrasena' => 'NuevaClave123'])
        ->assertOk();

    $usuario->refresh();

    expect($usuario->primer_login)->toBeFalse()
        ->and(Hash::check('NuevaClave123', $usuario->password))->toBeTrue();
});

test('un usuario sin servidor vinculado también puede completar el primer acceso', function () {
    $usuario = User::factory()->create([
        'password'     => Hash::make('Temporal2026'),
        'primer_login' => true,
    ]);

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/auth/cambiar-contrasena', ['nueva_contrasena' => 'NuevaClave123'])
        ->assertOk();
});
