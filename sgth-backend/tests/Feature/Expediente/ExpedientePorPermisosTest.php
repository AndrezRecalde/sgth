<?php

use App\Enums\Permiso;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El Expediente pasó del rol `admin-uath|asistente-uath` a sus permisos el
| 2026-10-28 (docs/revision-permisos-sin-uso.md, D2, D4 y D5): los mismos dos
| roles entran, el auditor sigue fuera, y lo que se gana es que TI puede dar o
| quitar cada parte desde Usuarios.
*/

beforeEach(function () {
    $unidad = unidadDePrueba(['nombre' => 'Unidad Expediente por Permisos']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0809999881', 'nombre' => 'Elena', 'apellido' => 'Permisos',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Expediente por Permisos')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->base = "/api/v1/expediente/servidores/{$this->servidor->id}";

    $this->migracion = fn () => require database_path('migrations/2026_12_08_000000_expediente_por_permisos.php');
});

/** Un usuario sin rol, con estos permisos adicionales, como los da TI desde Usuarios. */
function usuarioConPermisosDelExpediente(string ...$permisos): User
{
    $usuario = User::factory()->create();
    foreach ($permisos as $permiso) {
        $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'sanctum']));
    }

    return $usuario;
}

test('el seeder da el Expediente a talento humano y no al auditor', function () {
    $this->seed(RolPermisoSeeder::class);

    $nombres = array_map(fn (Permiso $p) => $p->value, RolPermisoSeeder::PERMISOS_EXPEDIENTE_TH);

    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        expect(Role::findByName($rol, 'sanctum')->hasAllPermissions($nombres))->toBeTrue("{$rol} debería tenerlos");
    }
    expect(Role::findByName('auditor', 'sanctum')->hasPermissionTo('ver-expediente-todos'))->toBeFalse();
});

test('el auditor sigue sin entrar al Expediente', function () {
    $this->seed(RolPermisoSeeder::class);
    $auditor = User::factory()->create();
    $auditor->assignRole('auditor');

    $this->actingAs($auditor, 'sanctum')->getJson('/api/v1/expediente/servidores')->assertForbidden();
    $this->actingAs($auditor, 'sanctum')->getJson($this->base)->assertForbidden();
});

test('quien tiene ver-expediente-todos lista y abre fichas, aunque no tenga el rol', function () {
    $usuario = usuarioConPermisosDelExpediente('ver-expediente-todos');

    $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/expediente/servidores')->assertOk();
    $this->actingAs($usuario, 'sanctum')->getJson($this->base)->assertOk();
    // Ver no es gestionar: los historiales piden gestionar-expediente.
    $this->actingAs($usuario, 'sanctum')->getJson("{$this->base}/cuentas-bancarias")->assertForbidden();
});

test('las cargas familiares van con su propio permiso, aparte de los historiales', function () {
    $historiales = usuarioConPermisosDelExpediente('gestionar-expediente');
    $cargas = usuarioConPermisosDelExpediente('gestionar-cargas-familiares');

    $this->actingAs($historiales, 'sanctum')->getJson("{$this->base}/cuentas-bancarias")->assertOk();
    $this->actingAs($historiales, 'sanctum')->getJson("{$this->base}/cargas-familiares")->assertForbidden();

    $this->actingAs($cargas, 'sanctum')->getJson("{$this->base}/cargas-familiares")->assertOk();
    $this->actingAs($cargas, 'sanctum')->getJson('/api/v1/expediente/cargas-por-validar')->assertOk();
    $this->actingAs($cargas, 'sanctum')->getJson("{$this->base}/cuentas-bancarias")->assertForbidden();
});

test('sin cargar-documentos el asistente ya no sube, y lo demás le sigue igual', function () {
    Storage::fake('local');
    Role::findByName('asistente-uath', 'sanctum')->revokePermissionTo('cargar-documentos');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');

    $this->actingAs($asistente, 'sanctum')
        ->post("{$this->base}/documentos", [
            'tipo_documento' => 'cedula_identidad',
            'archivo'        => UploadedFile::fake()->create('cedula.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
    $this->actingAs($asistente, 'sanctum')->getJson("{$this->base}/documentos")->assertOk();
});

test('la migración deja el acceso como estaba, y vuelve atrás', function () {
    // Como estaban los roles antes: el asistente sin cargar-documentos y el
    // auditor con ver-expediente-todos.
    Role::findByName('asistente-uath', 'sanctum')->revokePermissionTo('cargar-documentos');
    $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'sanctum']);
    $auditor->givePermissionTo('ver-expediente-todos');

    ($this->migracion)()->up();

    $nombres = array_map(fn (Permiso $p) => $p->value, RolPermisoSeeder::PERMISOS_EXPEDIENTE_TH);
    expect(Role::findByName('admin-uath', 'sanctum')->hasAllPermissions($nombres))->toBeTrue()
        ->and(Role::findByName('asistente-uath', 'sanctum')->hasAllPermissions($nombres))->toBeTrue()
        ->and($auditor->fresh()->hasPermissionTo('ver-expediente-todos'))->toBeFalse();

    ($this->migracion)()->down();

    expect(Role::findByName('asistente-uath', 'sanctum')->fresh()->hasPermissionTo('cargar-documentos'))->toBeFalse()
        ->and(Role::findByName('asistente-uath', 'sanctum')->fresh()->hasPermissionTo('gestionar-expediente'))->toBeTrue()
        ->and($auditor->fresh()->hasPermissionTo('ver-expediente-todos'))->toBeTrue();
});
