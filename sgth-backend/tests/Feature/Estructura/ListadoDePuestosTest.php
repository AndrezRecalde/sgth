<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El listado de Puestos y cargos. Un puesto con historia no se borra: se
| desactiva, y la pantalla tiene que poder separarlo de los vigentes.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum']));

    $usuario = User::factory()->create();
    $usuario->assignRole($rol);
    $this->actingAs($usuario, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección del Listado']);
});

test('el listado separa los puestos activos de los desactivados', function () {
    $activo = puestoDePrueba($this->unidad, 'Vigente');
    $inactivo = puestoDePrueba($this->unidad, 'Desactivado', ['activo' => false]);

    $ids = fn (string $consulta) => collect($this->getJson("/api/v1/estructura/puestos?{$consulta}")->assertOk()->json('datos'))
        ->pluck('id')->all();

    expect($ids('activo=true'))->toContain($activo->id)->not->toContain($inactivo->id);
    expect($ids('activo=false'))->toContain($inactivo->id)->not->toContain($activo->id);
    expect($ids('per_page=50'))->toContain($activo->id, $inactivo->id);
});
