<?php

use App\Enums\Permiso;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PuestoActividad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| Las actividades de un puesto (las columnas de la matriz de riesgos del FEMO)
| y el catálogo de cargos (de donde la ficha hereda el código CIUO) no
| comprobaban nada: cualquier usuario con sesión podía crearlos, cambiarlos o
| borrarlos. Editar un puesto tampoco pedía permiso, aunque crearlo sí.
*/

function usuarioEstructuraProteccion(string $rol, array $permisos = []): User
{
    $r = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    foreach ($permisos as $permiso) {
        $r->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }
    $u = User::factory()->create();
    $u->assignRole($r);

    return $u;
}

beforeEach(function () {
    $this->puesto = puestoDePrueba(unidadDePrueba());
    $this->actividad = PuestoActividad::create([
        'puesto_id' => $this->puesto->id, 'descripcion' => 'Mantenimiento eléctrico',
        'orden' => 1, 'activo' => true,
    ]);
    $this->rutaActividades = "/api/v1/estructura/puestos/{$this->puesto->id}/actividades";

    $this->servidor = usuarioEstructuraProteccion('servidor');
    $this->talentoHumano = usuarioEstructuraProteccion('admin-uath', [Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS]);
});

test('un servidor común no toca las actividades de un puesto', function () {
    $this->actingAs($this->servidor, 'sanctum');

    $this->getJson($this->rutaActividades)->assertStatus(403);
    $this->postJson($this->rutaActividades, ['descripcion' => 'Inventada'])->assertStatus(403);
    $this->patchJson("{$this->rutaActividades}/{$this->actividad->id}", ['descripcion' => 'Cambiada'])->assertStatus(403);
    $this->postJson("{$this->rutaActividades}/reordenar", ['orden' => [$this->actividad->id]])->assertStatus(403);
    $this->deleteJson("{$this->rutaActividades}/{$this->actividad->id}")->assertStatus(403);

    expect($this->actividad->fresh()->descripcion)->toBe('Mantenimiento eléctrico')
        ->and(PuestoActividad::count())->toBe(1);
});

test('Talento Humano gestiona las actividades', function () {
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->getJson($this->rutaActividades)->assertOk();
    $this->postJson($this->rutaActividades, ['descripcion' => 'Inspección de redes'])->assertCreated();
    $this->patchJson("{$this->rutaActividades}/{$this->actividad->id}", ['descripcion' => 'Mantenimiento'])->assertOk();
    $this->deleteJson("{$this->rutaActividades}/{$this->actividad->id}")->assertOk();
});

test('quien evalúa en el Dispensario lee las actividades, pero no las cambia', function (string $rol) {
    $this->actingAs(usuarioEstructuraProteccion($rol), 'sanctum');

    $this->getJson($this->rutaActividades)
        ->assertOk()
        ->assertJsonPath('datos.0.descripcion', 'Mantenimiento eléctrico');
    $this->postJson($this->rutaActividades, ['descripcion' => 'Inventada'])->assertStatus(403);
})->with(['medico', 'admin-dispensario']);

test('editar un puesto pide gestionar puestos', function () {
    $this->actingAs($this->servidor, 'sanctum')
        ->putJson("/api/v1/estructura/puestos/{$this->puesto->id}", ['plazas' => 9])
        ->assertStatus(403);
});

test('el catálogo de cargos se lee con sesión y se gestiona con permiso', function () {
    $cargo = Cargo::create(['nombre' => 'Chofer', 'codigo_ciuo' => '8322', 'activo' => true]);

    $this->actingAs($this->servidor, 'sanctum');
    $this->getJson('/api/v1/estructura/cargos')->assertOk();
    $this->postJson('/api/v1/estructura/cargos', ['nombre' => 'Inventado'])->assertStatus(403);
    $this->putJson("/api/v1/estructura/cargos/{$cargo->id}", ['codigo_ciuo' => '9999'])->assertStatus(403);
    $this->deleteJson("/api/v1/estructura/cargos/{$cargo->id}")->assertStatus(403);
    expect($cargo->fresh()->codigo_ciuo)->toBe('8322');

    $this->actingAs($this->talentoHumano, 'sanctum');
    $this->postJson('/api/v1/estructura/cargos', ['nombre' => 'Electricista', 'codigo_ciuo' => '7411'])->assertCreated();
    $this->putJson("/api/v1/estructura/cargos/{$cargo->id}", ['codigo_ciuo' => '8331'])->assertOk();
});

test('el 403 de cargos conserva su mensaje', function () {
    // exigirGestion() pasó de devolver la respuesta a lanzar (2026-10-04),
    // para que Scramble vuelva a tipar store/update/destroy.
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/estructura/cargos', ['nombre' => 'Inventado'])
        ->assertStatus(403)
        ->assertJsonPath('mensaje', 'No tiene permiso para gestionar cargos.');
});
