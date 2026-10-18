<?php

use App\Enums\EstadoComunicado;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
| Quién redacta, quién publica y quién ve las lecturas
| (docs/diseno-noticias-comunicados.md, 7.3).
|
| El punto delicado es D1: el asistente de Talento Humano redacta, pero
| publica solo si se le da `publicar-comunicados` como permiso adicional desde
| Usuarios. Ese permiso no va en su rol, para que no lo tengan todos los
| asistentes. Las pruebas usan los roles reales del seeder.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

const PERMISOS_DE_COMUNICADOS = ['publicar-comunicados', 'redactar-comunicados', 'ver-lecturas-comunicados'];

function permisosDeComunicadosDelRol(string $rol): array
{
    return Role::findByName($rol, 'sanctum')->permissions()
        ->whereIn('name', PERMISOS_DE_COMUNICADOS)
        ->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);
});

test('admin-uath redacta, publica y ve las lecturas; el asistente no publica por su rol', function () {
    expect(permisosDeComunicadosDelRol('admin-uath'))->toBe(PERMISOS_DE_COMUNICADOS)
        ->and(permisosDeComunicadosDelRol('asistente-uath'))->toBe(['redactar-comunicados', 'ver-lecturas-comunicados'])
        ->and(permisosDeComunicadosDelRol('auditor'))->toBe(['ver-lecturas-comunicados'])
        ->and(permisosDeComunicadosDelRol('admin-ti'))->toBe(PERMISOS_DE_COMUNICADOS)
        ->and(permisosDeComunicadosDelRol('analista-uath'))->toBe([])
        ->and(permisosDeComunicadosDelRol('servidor'))->toBe([])
        ->and(permisosDeComunicadosDelRol('comunicacion-social'))->toBe([]);
});

test('el asistente sin el permiso adicional deja el borrador listo, pero no lo publica', function () {
    $asistente = usuarioDeComunicados('asistente-uath');

    $id = $this->actingAs($asistente, 'sanctum')
        ->postJson('/api/v1/comunicados', datosDeComunicado())
        ->assertCreated()
        ->json('datos.id');

    $this->actingAs($asistente, 'sanctum')
        ->postJson("/api/v1/comunicados/{$id}/publicar")
        ->assertForbidden();

    expect(App\Models\Comunicados\Comunicado::find($id)->estado)->toBe(EstadoComunicado::BORRADOR);
});

test('con publicar-comunicados como permiso adicional, ese asistente publica', function () {
    $asistente = usuarioDeComunicados('asistente-uath');
    $otroAsistente = usuarioDeComunicados('asistente-uath');
    servidorDeComunicados(unidadDeComunicados('Dirección de Talento Humano'));

    // Lo que hace la pantalla Usuarios › Permisos adicionales.
    $admin = usuarioDeComunicados('admin-ti');
    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/v1/admin/usuarios/{$asistente->id}/permisos", ['permisos' => ['publicar-comunicados']])
        ->assertOk();

    $comunicado = borradorDeComunicado($asistente);

    $this->actingAs($asistente->fresh(), 'sanctum')
        ->postJson("/api/v1/comunicados/{$comunicado->id}/publicar")
        ->assertOk()
        ->assertJsonPath('datos.vigencia', 'publicado');

    // El permiso es de la persona, no del rol: el otro asistente sigue sin él.
    $otro = borradorDeComunicado($otroAsistente);
    $this->actingAs($otroAsistente, 'sanctum')
        ->postJson("/api/v1/comunicados/{$otro->id}/publicar")
        ->assertForbidden();
});

test('el permiso aparece en Permisos adicionales bajo su propio módulo', function () {
    $admin = usuarioDeComunicados('admin-ti');

    $modulo = collect($this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/admin/permisos')
        ->assertOk()
        ->json('datos'))->firstWhere('modulo', 'Noticias y comunicados');

    expect(collect($modulo['permisos'])->pluck('nombre')->sort()->values()->all())->toBe(PERMISOS_DE_COMUNICADOS);
});

test('el auditor consulta pero no redacta ni publica', function () {
    $auditor = usuarioDeComunicados('auditor');
    $comunicado = borradorDeComunicado(usuarioDeComunicados('admin-uath'));

    $this->actingAs($auditor, 'sanctum')->getJson('/api/v1/comunicados')->assertOk();
    $this->actingAs($auditor, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}")->assertOk();
    $this->actingAs($auditor, 'sanctum')->postJson('/api/v1/comunicados', datosDeComunicado())->assertForbidden();
    $this->actingAs($auditor, 'sanctum')->postJson("/api/v1/comunicados/{$comunicado->id}/publicar")->assertForbidden();
});

test('un servidor no entra a la gestión', function (string $rol) {
    $usuario = usuarioDeComunicados($rol);
    $comunicado = borradorDeComunicado(usuarioDeComunicados('admin-uath'));

    $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/comunicados')->assertForbidden();
    $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}")->assertForbidden();
    $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/comunicados/{$comunicado->id}/portada")->assertForbidden();
    $this->actingAs($usuario, 'sanctum')->postJson('/api/v1/comunicados', datosDeComunicado())->assertForbidden();
})->with(['servidor', 'jefe-unidad', 'director', 'analista-uath', 'comunicacion-social']);

test('la migración da los permisos a los roles que ya existen, y publicar no va al asistente', function () {
    foreach (['admin-ti', 'admin-uath', 'asistente-uath', 'auditor'] as $rol) {
        Role::findByName($rol, 'sanctum')->revokePermissionTo(
            array_intersect(PERMISOS_DE_COMUNICADOS, permisosDeComunicadosDelRol($rol))
        );
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migracion = require database_path('migrations/2026_11_10_000100_permisos_de_comunicados.php');
    $migracion->up();

    expect(permisosDeComunicadosDelRol('admin-uath'))->toBe(PERMISOS_DE_COMUNICADOS)
        ->and(permisosDeComunicadosDelRol('admin-ti'))->toBe(PERMISOS_DE_COMUNICADOS)
        ->and(permisosDeComunicadosDelRol('asistente-uath'))->toBe(['redactar-comunicados', 'ver-lecturas-comunicados'])
        ->and(permisosDeComunicadosDelRol('auditor'))->toBe(['ver-lecturas-comunicados'])
        ->and(permisosDeComunicadosDelRol('jefe-unidad'))->toBe([]);
});

test('la migración no le quita a nadie el permiso adicional que ya tenía', function () {
    $asistente = usuarioDeComunicados('asistente-uath');
    $asistente->givePermissionTo('publicar-comunicados');

    $migracion = require database_path('migrations/2026_11_10_000100_permisos_de_comunicados.php');
    $migracion->up();

    expect(User::find($asistente->id)->hasDirectPermission('publicar-comunicados'))->toBeTrue();
});
