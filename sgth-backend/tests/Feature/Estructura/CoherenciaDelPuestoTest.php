<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Lo que un puesto cumple entre sus propios campos (CoherenciaDelPuesto). La
| jefatura de dos plazas pasaba la validación y la base la rechazaba con un
| 500; el rol «Código del Trabajo» se guardaba en un puesto LOSEP.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    foreach ([Permiso::GESTIONAR_PUESTOS, Permiso::VER_ESTRUCTURA] as $permiso) {
        $rol->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }

    $this->user = User::factory()->create();
    $this->user->assignRole($rol);
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Coherencia']);
    $this->cargo = Cargo::create(['nombre' => 'Director de Coherencia']);
    $this->nuevo = fn (array $datos) => $this->postJson('/api/v1/estructura/puestos', $datos + [
        'cargo_id'                 => $this->cargo->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral'          => 'losep',
    ]);
});

test('una jefatura con dos plazas se rechaza en las plazas, sin llegar a la base', function () {
    ($this->nuevo)(['plazas' => 2, 'es_jefe' => true])
        ->assertStatus(422)
        ->assertJsonPath('errores.plazas.0', fn (string $m) => str_contains($m, 'una sola plaza'));

    expect(Puesto::where('unidad_administrativa_id', $this->unidad->id)->exists())->toBeFalse();

    ($this->nuevo)(['plazas' => 1, 'es_jefe' => true])->assertCreated();
});

test('al editar, cuenta lo que el puesto ya tiene guardado', function () {
    $jefatura = puestoDePrueba($this->unidad, 'Director', ['es_jefe' => true, 'plazas' => 1]);
    $analista = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 3]);

    // Subirle las plazas a la jefatura, sin volver a enviar `es_jefe`.
    $this->putJson("/api/v1/estructura/puestos/{$jefatura->id}", ['plazas' => 2])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['plazas']]);

    // Marcar como jefatura un puesto de tres plazas, sin enviar las plazas.
    $jefatura->update(['es_jefe' => false]);
    $this->putJson("/api/v1/estructura/puestos/{$analista->id}", ['es_jefe' => true])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['plazas']]);

    expect($analista->fresh()->es_jefe)->toBeFalse();
});

test('el rol «Código del Trabajo» va solo con ese régimen, y ese régimen no lleva un rol de la LOSEP', function () {
    ($this->nuevo)(['plazas' => 1, 'rol_puesto' => 'codigo_trabajo'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['rol_puesto']]);

    ($this->nuevo)(['plazas' => 1, 'regimen_laboral' => 'codigo_trabajo', 'rol_puesto' => 'administrativo'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['rol_puesto']]);

    ($this->nuevo)(['plazas' => 1, 'regimen_laboral' => 'codigo_trabajo', 'rol_puesto' => 'codigo_trabajo'])->assertCreated();
    ($this->nuevo)(['plazas' => 1, 'regimen_laboral' => 'codigo_trabajo'])->assertCreated();
});

test('la edición devuelve el puesto con el cargo nuevo, no con el de antes', function () {
    $puesto = puestoDePrueba($this->unidad, 'Asistente');
    $otro = Cargo::create(['nombre' => 'Secretario']);

    $this->putJson("/api/v1/estructura/puestos/{$puesto->id}", ['cargo_id' => $otro->id])
        ->assertOk()
        ->assertJsonPath('datos.cargo.nombre', 'Secretario');
});

test('el mensaje de la jefatura repetida dice que cuenta también la desactivada', function () {
    puestoDePrueba($this->unidad, 'Director', ['es_jefe' => true, 'plazas' => 1, 'activo' => false]);

    ($this->nuevo)(['plazas' => 1, 'es_jefe' => true])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'aunque esté desactivado'));
});

test('el listado no entrega más de cien puestos por página', function () {
    $this->getJson('/api/v1/estructura/puestos?per_page=100000')
        ->assertOk()
        ->assertJsonPath('meta.por_pagina', 100);
});

test('un id que no es número da 404 y no 500', function () {
    $this->putJson('/api/v1/estructura/cargos/abc', ['nombre' => 'X'])->assertNotFound();
    $this->getJson('/api/v1/estructura/puestos/abc/actividades')->assertNotFound();
    $puesto = puestoDePrueba($this->unidad, 'Asistente');
    $this->patchJson("/api/v1/estructura/puestos/{$puesto->id}/actividades/abc", [])->assertNotFound();
});

test('sin permiso para gestionar puestos, las actividades responden 403 antes que 422', function () {
    $puesto = puestoDePrueba($this->unidad, 'Asistente');
    $sinPermiso = User::factory()->create();

    $this->actingAs($sinPermiso, 'sanctum')
        ->postJson("/api/v1/estructura/puestos/{$puesto->id}/actividades", [])
        ->assertForbidden();
});
