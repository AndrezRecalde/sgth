<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Las rutas de Enfermería y las acciones sobre turnos vivían dentro del grupo
 * `dispensario` sin `role:`: cualquier servidor con sesión podía buscar
 * pacientes por cédula, leer las atenciones de enfermería de otros, anularlas
 * o marcar un turno como no presentado. Estas pruebas fijan quién entra.
 */

function usuarioEnfermeriaAcceso(string $usuario, ?string $rol = null): User
{
    $user = User::forceCreate([
        'email'        => "{$usuario}@example.com",
        'usuario_ti'   => $usuario,
        'password'     => bcrypt('123456'),
        'primer_login' => false,
    ]);

    if ($rol !== null) {
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));
    }

    return $user;
}

dataset('rutas_de_enfermeria', [
    'buscar paciente'       => ['get',   '/api/v1/dispensario/pacientes/buscar?cedula=0801234561'],
    'listar atenciones'     => ['get',   '/api/v1/dispensario/atenciones-enfermeria'],
    'registrar atención'    => ['post',  '/api/v1/dispensario/atenciones-enfermeria'],
    'anular atención'       => ['patch', '/api/v1/dispensario/atenciones-enfermeria/1/anular'],
    'catálogo de servicios' => ['get',   '/api/v1/dispensario/catalogo-servicios-enfermeria'],
    'pendientes de triaje'  => ['get',   '/api/v1/dispensario/triaje/pendientes'],
    'listos para consulta'  => ['get',   '/api/v1/dispensario/agenda/listos-para-consulta'],
    'turnos del día'        => ['get',   '/api/v1/dispensario/agenda/turnos-del-dia'],
    'turno por folio'       => ['get',   '/api/v1/dispensario/agenda/por-folio/TUR-2026-00001'],
    'no presentado'         => ['patch', '/api/v1/dispensario/agenda/1/no-presentado'],
    'reactivar'             => ['patch', '/api/v1/dispensario/agenda/1/reactivar'],
    'en consulta'           => ['patch', '/api/v1/dispensario/agenda/1/en-consulta'],
]);

test('un servidor sin rol del dispensario no entra', function (string $metodo, string $uri) {
    $this->actingAs(usuarioEnfermeriaAcceso('cualquiera'), 'sanctum');

    $this->json($metodo, $uri)->assertForbidden();
})->with('rutas_de_enfermeria');

test('el médico consulta pero no registra ni anula servicios de enfermería', function () {
    $this->actingAs(usuarioEnfermeriaAcceso('medico1', 'medico'), 'sanctum');

    $this->getJson('/api/v1/dispensario/atenciones-enfermeria')->assertOk();
    $this->getJson('/api/v1/dispensario/triaje/pendientes')->assertOk();

    $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [])->assertForbidden();
    $this->patchJson('/api/v1/dispensario/atenciones-enfermeria/1/anular', [])->assertForbidden();
    $this->getJson('/api/v1/dispensario/catalogo-servicios-enfermeria')->assertForbidden();
});

test('enfermería sí entra a lo suyo', function () {
    $this->actingAs(usuarioEnfermeriaAcceso('enfermera1', 'enfermera'), 'sanctum');

    $this->getJson('/api/v1/dispensario/atenciones-enfermeria')->assertOk();
    $this->getJson('/api/v1/dispensario/catalogo-servicios-enfermeria')->assertOk();
    $this->getJson('/api/v1/dispensario/triaje/pendientes')->assertOk();
    $this->getJson('/api/v1/dispensario/agenda/turnos-del-dia')->assertOk();
    // Registrar sin datos ya no es un 403: pasa el rol y lo frena la validación.
    $this->postJson('/api/v1/dispensario/atenciones-enfermeria', [])->assertUnprocessable();
});
