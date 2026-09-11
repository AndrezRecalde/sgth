<?php

use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El login devolvía el modelo crudo, sin roles ni permisos. El frontend lo
 * guarda tal cual y se caía en cualquier pantalla que los consultara, hasta que
 * la portada pedía `auth/perfil`. Ahora las dos rutas entregan el mismo usuario.
 */

const CLAVE_SESION_DE_PRUEBA = 'ClaveSegura123';

function usuarioParaIniciarSesion(array $atributos = []): User
{
    return User::factory()->create(array_merge([
        'usuario_ti'   => 'mlopez',
        'password'     => Hash::make(CLAVE_SESION_DE_PRUEBA),
        'primer_login' => false,
    ], $atributos));
}

function iniciarSesionComoMlopez(): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'usuario'    => 'mlopez',
        'contrasena' => CLAVE_SESION_DE_PRUEBA,
    ]);
}

function servidorConSesionDePrueba(): Servidor
{
    UnidadAdministrativa::unguard();
    Puesto::unguard();

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Talento Humano']);

    return Servidor::create([
        'cedula'                   => '0912345678',
        'nombre'                   => 'María',
        'apellido'                 => 'López',
        'puesto_id'                => puestoDePrueba($unidad, 'Analista de Talento Humano')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
        'puede_marcar'             => true,
    ]);
}

test('el login devuelve los roles y los permisos del usuario', function () {
    $rol = Role::firstOrCreate(['name' => 'asistente-de-prueba', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(
        Permission::firstOrCreate(['name' => 'gestionar-vacaciones', 'guard_name' => 'sanctum'])
    );

    $usuario = usuarioParaIniciarSesion();
    $usuario->assignRole($rol);
    // Un permiso directo, fuera del rol: también tiene que llegar.
    $usuario->givePermissionTo(
        Permission::firstOrCreate(['name' => 'ver-asistencia-todos', 'guard_name' => 'sanctum'])
    );

    $respuesta = iniciarSesionComoMlopez();

    $respuesta->assertOk()
        ->assertJsonStructure([
            'datos' => [
                'token',
                'primer_login',
                'usuario' => ['id', 'nombre_completo', 'email', 'usuario_ti', 'roles', 'permisos', 'servidor'],
            ],
        ])
        ->assertJsonPath('datos.usuario.roles', ['asistente-de-prueba']);

    expect($respuesta->json('datos.usuario.permisos'))
        ->toEqualCanonicalizing(['gestionar-vacaciones', 'ver-asistencia-todos']);
});

test('sin roles el login devuelve listas vacías, no las omite', function () {
    usuarioParaIniciarSesion();

    iniciarSesionComoMlopez()
        ->assertOk()
        ->assertJsonPath('datos.usuario.roles', [])
        ->assertJsonPath('datos.usuario.permisos', [])
        ->assertJsonPath('datos.usuario.servidor', null);
});

test('el login y el perfil devuelven exactamente el mismo usuario', function () {
    $rol = Role::firstOrCreate(['name' => 'asistente-de-prueba', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(
        Permission::firstOrCreate(['name' => 'gestionar-vacaciones', 'guard_name' => 'sanctum'])
    );

    $usuario = usuarioParaIniciarSesion(['servidor_id' => servidorConSesionDePrueba()->id]);
    $usuario->assignRole($rol);

    $delLogin = iniciarSesionComoMlopez()->assertOk()->json('datos.usuario');

    $delPerfil = $this->actingAs($usuario->fresh(), 'sanctum')
        ->getJson('/api/v1/auth/perfil')
        ->assertOk()
        ->json('datos');

    expect($delLogin)->toBe($delPerfil);
});

test('el usuario trae los datos del servidor que usa el frontend', function () {
    $servidor = servidorConSesionDePrueba();
    usuarioParaIniciarSesion(['servidor_id' => $servidor->id]);

    iniciarSesionComoMlopez()
        ->assertOk()
        ->assertJsonPath('datos.usuario.servidor.id', $servidor->id)
        ->assertJsonPath('datos.usuario.servidor.cedula', '0912345678')
        // La marcación en línea lo lee del usuario guardado; el perfil no lo traía.
        ->assertJsonPath('datos.usuario.servidor.puede_marcar', true)
        ->assertJsonPath('datos.usuario.servidor.regimen_laboral', RegimenLaboral::LOSEP->value)
        ->assertJsonPath('datos.usuario.servidor.unidad_administrativa_id', $servidor->unidad_administrativa_id)
        ->assertJsonPath('datos.usuario.servidor.puesto.nombre', 'Analista de Talento Humano')
        ->assertJsonPath('datos.usuario.servidor.puesto.es_jefe', false)
        ->assertJsonPath('datos.usuario.servidor.unidad_administrativa.nombre', 'Dirección de Talento Humano');
});

test('el login ya no expone el modelo crudo', function () {
    usuarioParaIniciarSesion();

    $usuario = iniciarSesionComoMlopez()->assertOk()->json('datos.usuario');

    expect($usuario)->not->toHaveKeys(['password', 'remember_token', 'created_at', 'updated_at']);
});
