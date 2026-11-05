<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Cargo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El catálogo de cargos: nombres sin repetir, y cuántos puestos usa cada uno,
| que es lo que decide si se puede eliminar.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    foreach ([Permiso::GESTIONAR_PUESTOS, Permiso::VER_ESTRUCTURA] as $permiso) {
        $rol->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }

    $usuario = User::factory()->create();
    $usuario->assignRole($rol);
    $this->actingAs($usuario, 'sanctum');
});

test('no se crea un cargo con el nombre de otro, sin importar mayúsculas ni espacios', function () {
    Cargo::create(['nombre' => 'Analista de Bodega']);

    $this->postJson('/api/v1/estructura/cargos', ['nombre' => '  analista de BODEGA '])
        ->assertStatus(422)
        ->assertJsonPath('errores.nombre.0', 'Ya hay un cargo con ese nombre en el catálogo.');

    expect(Cargo::count())->toBe(1);
});

test('un cargo borrado no reserva su nombre', function () {
    Cargo::create(['nombre' => 'Guardalmacén'])->delete();

    $this->postJson('/api/v1/estructura/cargos', ['nombre' => 'Guardalmacén'])->assertCreated();
});

test('renombrar a un nombre ocupado se rechaza; editar lo demás sin tocar el nombre, no', function () {
    $chofer = Cargo::create(['nombre' => 'Chofer']);
    Cargo::create(['nombre' => 'Mensajero']);

    $this->putJson("/api/v1/estructura/cargos/{$chofer->id}", ['nombre' => 'mensajero'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['nombre']]);

    // Dos iguales que ya existían: corregir el CIUO de uno no lo bloquea el otro.
    $repetido = Cargo::create(['nombre' => 'Mensajero']);
    $this->putJson("/api/v1/estructura/cargos/{$repetido->id}", ['nombre' => 'Mensajero', 'codigo_ciuo' => '9621'])
        ->assertOk();
});

test('el listado dice cuántos puestos usa cada cargo', function () {
    $unidad = unidadDePrueba(['nombre' => 'Dirección de Cargos']);
    $usado = puestoDePrueba($unidad, 'Técnico de Campo')->cargo;
    $libre = Cargo::create(['nombre' => 'Sin puestos']);

    $cargos = collect($this->getJson('/api/v1/estructura/cargos')->assertOk()->json('datos'))->keyBy('id');

    expect($cargos[$usado->id]['puestos_count'])->toBe(1)
        ->and($cargos[$libre->id]['puestos_count'])->toBe(0);
});

test('la edición ya no desactiva un cargo', function () {
    $cargo = Cargo::create(['nombre' => 'Electricista']);

    $this->putJson("/api/v1/estructura/cargos/{$cargo->id}", ['activo' => false])->assertOk();

    expect($cargo->fresh()->activo)->toBeTrue();
});
