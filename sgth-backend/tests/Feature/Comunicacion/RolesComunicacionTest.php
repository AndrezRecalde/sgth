<?php

use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Los roles y permisos de Comunicación Social
| (docs/diseno-comunicacion-social.md, 7.3).
|
| El punto delicado es D2: los logos salen en todos los documentos, así que no
| los cambia el equipo de Comunicación sino la persona designada, con un rol
| propio. Las pruebas usan los roles reales del seeder.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

const PERMISOS_DE_COMUNICACION = [
    'gestionar-fotografias', 'gestionar-credenciales', 'ver-credenciales',
    'gestionar-identidad-institucional',
];

function permisosDeComunicacionDelRol(string $rol): array
{
    return Role::findByName($rol, 'sanctum')->permissions()
        ->whereIn('name', PERMISOS_DE_COMUNICACION)
        ->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
});

test('el equipo de comunicacion toma fotos e imprime credenciales, pero no cambia los logos', function () {
    $rol = Role::findByName('comunicacion-social', 'sanctum');

    expect(permisosDeComunicacionDelRol('comunicacion-social'))
        ->toBe(['gestionar-credenciales', 'gestionar-fotografias', 'ver-credenciales'])
        ->and($rol->hasPermissionTo('gestionar-identidad-institucional'))->toBeFalse()
        // Es un servidor más: tiene el Portal y su credencial.
        ->and($rol->hasPermissionTo('acceso-autoservicio'))->toBeTrue();
});

test('el responsable de identidad cambia los logos y nada mas de comunicacion', function () {
    $rol = Role::findByName('responsable-identidad', 'sanctum');

    expect(permisosDeComunicacionDelRol('responsable-identidad'))
        ->toBe(['gestionar-identidad-institucional'])
        ->and($rol->hasPermissionTo('acceso-autoservicio'))->toBeTrue();
});

test('admin-ti lo tiene todo y talento humano y el auditor solo consultan credenciales', function () {
    expect(permisosDeComunicacionDelRol('admin-ti'))
        ->toBe(collect(PERMISOS_DE_COMUNICACION)->sort()->values()->all())
        ->and(permisosDeComunicacionDelRol('admin-uath'))->toBe(['ver-credenciales'])
        ->and(permisosDeComunicacionDelRol('auditor'))->toBe(['ver-credenciales'])
        ->and(permisosDeComunicacionDelRol('servidor'))->toBe([])
        ->and(permisosDeComunicacionDelRol('director'))->toBe([]);
});

test('los dos roles se suman en una misma persona', function () {
    $usuario = User::factory()->create(['primer_login' => false]);
    $usuario->assignRole(['comunicacion-social', 'responsable-identidad']);

    expect(collect(PERMISOS_DE_COMUNICACION)->every(fn ($p) => $usuario->hasPermissionTo($p)))->toBeTrue();
});

test('la pantalla de usuarios muestra los roles con su nombre y los permisos en su modulo', function () {
    $admin = User::factory()->create(['primer_login' => false]);
    $admin->assignRole('admin-ti');

    $roles = collect($this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/usuarios-roles')
        ->assertOk()
        ->json('datos'))->pluck('etiqueta', 'valor');

    expect($roles['comunicacion-social'])->toBe('Comunicación Social')
        ->and($roles['responsable-identidad'])->toBe('Responsable de Identidad Institucional');

    $modulo = collect($this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/permisos')
        ->assertOk()
        ->json('datos'))->firstWhere('modulo', 'Comunicación');

    expect(collect($modulo['permisos'])->pluck('nombre')->sort()->values()->all())
        ->toBe(collect(PERMISOS_DE_COMUNICACION)->sort()->values()->all());
});

// ── Migración ──────────────────────────────────────────────────────────────

test('la migracion crea los roles y deja los permisos como el seeder', function () {
    $todos = fn (string $rol) => Role::findByName($rol, 'sanctum')
        ->permissions()->pluck('name')->sort()->values()->all();

    $delSeeder = [];
    foreach (['comunicacion-social', 'responsable-identidad', 'admin-ti', 'admin-uath', 'auditor'] as $rol) {
        $delSeeder[$rol] = $todos($rol);
    }

    $migracion = require database_path('migrations/2026_11_01_000000_roles_y_permisos_de_comunicacion.php');

    // La base de un despliegue anterior.
    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::where('name', 'comunicacion-social')->exists())->toBeFalse()
        ->and(Role::where('name', 'responsable-identidad')->exists())->toBeFalse()
        ->and(permisosDeComunicacionDelRol('admin-ti'))->toBe([]);

    $migracion->up();
    $migracion->up(); // idempotente
    // Su lista base todavía da el rol de pago propio: lo quita la migración
    // que borra las tablas de la nómina (2026-10-20).
    (require database_path('migrations/2026_11_14_000000_borrar_tablas_de_nomina.php'))->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($delSeeder as $rol => $esperados) {
        expect($todos($rol))->toBe($esperados);
    }
});
