<?php

use App\Enums\Permiso;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Régimen disciplinario pasó del rol admin-uath a sus tres permisos el
| 2026-10-28 (docs/revision-permisos-sin-uso.md, D8): leer, gestionar y
| sancionar. Los tres los tiene solo admin-uath, como antes.
*/

beforeEach(function () {
    $this->migracion = fn () => require database_path('migrations/2026_12_09_000000_disciplinario_por_permisos.php');
});

/** Un usuario sin rol, con estos permisos adicionales, como los da TI desde Usuarios. */
function usuarioConPermisosDisciplinarios(string ...$permisos): User
{
    $usuario = User::factory()->create();
    foreach ($permisos as $permiso) {
        $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']));
    }

    return $usuario;
}

test('el seeder da los tres solo a admin-uath', function () {
    $this->seed(RolPermisoSeeder::class);

    $nombres = array_map(fn (Permiso $p) => $p->value, RolPermisoSeeder::PERMISOS_DISCIPLINARIO);
    $conAlguno = Role::where('guard_name', 'sanctum')
        ->whereHas('permissions', fn ($q) => $q->whereIn('name', $nombres))
        ->pluck('name')
        ->all();

    expect($conAlguno)->toBe(['admin-uath'])
        ->and(Role::findByName('admin-uath', 'sanctum')->hasAllPermissions($nombres))->toBeTrue();
});

test('ver-sumarios lee el módulo pero no escribe', function () {
    $lector = usuarioConPermisosDisciplinarios('ver-sumarios');

    $this->actingAs($lector, 'sanctum')->getJson('/api/v1/disciplinario/sumarios')->assertOk();
    $this->actingAs($lector, 'sanctum')->getJson('/api/v1/disciplinario/vistos-buenos')->assertOk();
    $this->actingAs($lector, 'sanctum')->getJson('/api/v1/disciplinario/reglamento-interno/faltas')->assertOk();

    $this->actingAs($lector, 'sanctum')->postJson('/api/v1/disciplinario/sumarios', [])->assertForbidden();
    $this->actingAs($lector, 'sanctum')->postJson('/api/v1/disciplinario/vistos-buenos', [])->assertForbidden();
    $this->actingAs($lector, 'sanctum')->postJson('/api/v1/disciplinario/reglamento-interno/faltas', [])->assertForbidden();
});

test('gestionar sin registrar-sancion abre trámites pero no resuelve sumarios', function () {
    $gestor = usuarioConPermisosDisciplinarios('ver-sumarios', 'gestionar-sumarios');

    // Pasa la guarda y llega a la validación.
    $this->actingAs($gestor, 'sanctum')->postJson('/api/v1/disciplinario/sumarios', [])->assertStatus(422);
    $this->actingAs($gestor, 'sanctum')->postJson('/api/v1/disciplinario/sumarios/1/resolver', [])->assertForbidden();

    $sancionador = usuarioConPermisosDisciplinarios('registrar-sancion');
    $this->actingAs($sancionador, 'sanctum')->postJson('/api/v1/disciplinario/sumarios/1/resolver', [])
        ->assertStatus(422);
});

test('el asistente sigue fuera del módulo', function () {
    $asistente = User::factory()->create();
    $asistente->assignRole(Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']));

    $this->actingAs($asistente, 'sanctum')->getJson('/api/v1/disciplinario/sumarios')->assertForbidden();
});

test('la migración le asegura los tres a admin-uath', function () {
    $admin = Role::findByName('admin-uath', 'sanctum');
    $admin->syncPermissions([]);

    ($this->migracion)()->up();

    $nombres = array_map(fn (Permiso $p) => $p->value, RolPermisoSeeder::PERMISOS_DISCIPLINARIO);
    expect($admin->fresh()->hasAllPermissions($nombres))->toBeTrue();
});
