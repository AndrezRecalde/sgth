<?php

/*
| Acciones que iban por rol, o sin guarda, y pasan a su permiso
| (docs/plan-roles-y-accesos.md, PR 14).
|
| - Escribir partidas presupuestarias pedía `role:admin-uath`; ahora
|   `gestionar-distributivo`, que existía y ninguna ruta usaba.
| - Registrar la ficha de un servidor lo decidía `ServidorPolicy::crear` por el
|   rol admin-uath; ahora `registrar-ficha-servidor`, y el botón del
|   Expediente se guía por el mismo permiso.
*/

use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };
});

// ── Partidas presupuestarias ──────────────────────────────────────────────

test('admin-uath crea, edita y elimina partidas', function () {
    $this->actingAs(($this->conRol)('admin-uath'), 'sanctum');

    $id = $this->postJson('/api/v1/estructura/partidas-presupuestarias', [
        'codigo' => '510105', 'descripcion' => 'Remuneraciones unificadas de prueba',
    ])->assertCreated()->json('datos.id');

    $this->putJson("/api/v1/estructura/partidas-presupuestarias/{$id}", [
        'codigo' => '510105', 'descripcion' => 'Remuneraciones unificadas',
    ])->assertOk();

    $this->deleteJson("/api/v1/estructura/partidas-presupuestarias/{$id}")->assertSuccessful();
});

test('quien ve la estructura sin gestionar el distributivo no escribe partidas', function () {
    $partida = PartidaPresupuestaria::create(['codigo' => '510106', 'descripcion' => 'Salarios de prueba']);

    foreach (['asistente-uath', 'director', 'jefe-unidad', 'auditor', 'admin-ti'] as $rol) {
        $this->actingAs(($this->conRol)($rol), 'sanctum');

        $this->getJson('/api/v1/estructura/partidas-presupuestarias')->assertOk();
        $this->postJson('/api/v1/estructura/partidas-presupuestarias', [
            'codigo' => '510199', 'descripcion' => 'No debería crearse',
        ])->assertForbidden();
        $this->putJson("/api/v1/estructura/partidas-presupuestarias/{$partida->id}", [
            'codigo' => '510106', 'descripcion' => 'Cambiada',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/estructura/partidas-presupuestarias/{$partida->id}")->assertForbidden();
    }
});

test('el permiso del distributivo basta, sin el rol', function () {
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('gestionar-distributivo');

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/estructura/partidas-presupuestarias', [
            'codigo' => '510107', 'descripcion' => 'Por permiso suelto',
        ])
        ->assertCreated();
});

// ── Registrar ficha ───────────────────────────────────────────────────────

test('solo admin-uath registra fichas de servidores', function () {
    expect(($this->conRol)('admin-uath')->can('crear', Servidor::class))->toBeTrue();

    foreach (['asistente-uath', 'director', 'auditor', 'admin-ti', 'servidor'] as $rol) {
        expect(($this->conRol)($rol)->can('crear', Servidor::class))->toBeFalse("{$rol} no debería registrar fichas");
    }
});

test('el permiso de registrar fichas basta, sin el rol', function () {
    $usuario = User::factory()->create();
    $usuario->givePermissionTo('registrar-ficha-servidor');

    expect($usuario->can('crear', Servidor::class))->toBeTrue();
});

test('asistente-uath recibe 403 al registrar una ficha completa', function () {
    // Completa: el FormRequest valida antes de que el controlador autorice, y
    // una ficha a medias daría 422 sin llegar a la policy.
    $ficha = [
        'cedula' => '0808080801', 'nombre' => 'Prueba', 'apellido' => 'Sin Permiso',
        'fecha_nacimiento' => '1990-05-05', 'genero' => 'femenino', 'estado_civil' => 'soltero',
        'es_extranjero' => true, 'nacionalidad' => 'Colombiana', 'pais_origen' => 'Colombia',
    ];

    $this->actingAs(($this->conRol)('asistente-uath'), 'sanctum')
        ->postJson('/api/v1/expediente/servidores/basico', $ficha)
        ->assertForbidden();

    expect(Servidor::where('cedula', '0808080801')->exists())->toBeFalse();

    $this->actingAs(($this->conRol)('admin-uath'), 'sanctum')
        ->postJson('/api/v1/expediente/servidores/basico', $ficha)
        ->assertCreated();
});

test('la migracion da el permiso a admin-uath, como el seeder', function () {
    $migracion = require database_path('migrations/2026_12_02_000000_permiso_registrar_ficha_servidor.php');
    $roles = (new ReflectionClassConstant($migracion, 'ROLES'))->getValue();

    $delSeeder = Role::where('guard_name', 'sanctum')
        ->whereHas('permissions', fn ($q) => $q->where('name', 'registrar-ficha-servidor'))
        ->pluck('name')->sort()->values()->all();

    expect($delSeeder)->toBe(collect($roles)->sort()->values()->all());

    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Permission::where('name', 'registrar-ficha-servidor')->exists())->toBeFalse();

    $migracion->up();
    $migracion->up(); // idempotente
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('admin-uath', 'sanctum')->hasPermissionTo('registrar-ficha-servidor'))->toBeTrue();
});
