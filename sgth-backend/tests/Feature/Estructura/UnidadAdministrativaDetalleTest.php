<?php

use App\Enums\Permiso;
use App\Models\Estructura\TipoUnidad;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'gestor-estructura', 'guard_name' => 'sanctum']);
    foreach ([Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_ORGANIGRAMA] as $permiso) {
        $rol->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum'])
        );
    }

    $this->gestor = User::factory()->create(['primer_login' => false]);
    $this->gestor->assignRole($rol);
});

test('el detalle de una unidad incluye su tipo de proceso', function () {
    $tipo = TipoUnidad::firstOrCreate(
        ['id' => '44444444-4444-4444-4444-444444444444'],
        ['acronimo' => 'AV', 'descripcion' => 'AGREGADORES DE VALOR']
    );

    $unidad = UnidadAdministrativa::create([
        'codigo'         => 'UA-77',
        'nombre'         => 'Gestión Ambiental',
        'tipo_unidad_id' => $tipo->id,
        'nivel'          => 2,
        'estado'         => true,
    ]);

    // El formulario de edición se llena con esta respuesta: si el tipo no
    // viaja, actualizar cualquier otro campo lo dejaba en blanco.
    $this->actingAs($this->gestor, 'sanctum')
        ->getJson("/api/v1/estructura/unidades-administrativas/{$unidad->id}")
        ->assertStatus(200)
        ->assertJsonPath('datos.tipo_unidad.id', $tipo->id)
        ->assertJsonPath('datos.tipo_unidad.acronimo', 'AV');
});

test('editar una unidad conserva el tipo de proceso que no se tocó', function () {
    $tipo = TipoUnidad::firstOrCreate(
        ['id' => '33333333-3333-3333-3333-333333333333'],
        ['acronimo' => 'HA', 'descripcion' => 'HABILITANTES ASESORES']
    );

    $unidad = UnidadAdministrativa::create([
        'codigo'         => 'UA-78',
        'nombre'         => 'Gestión de Planificación',
        'tipo_unidad_id' => $tipo->id,
        'nivel'          => 2,
        'estado'         => true,
    ]);

    $this->actingAs($this->gestor, 'sanctum')
        ->putJson("/api/v1/estructura/unidades-administrativas/{$unidad->id}", [
            'nombre'         => 'Gestión de Planificación Institucional',
            'tipo_unidad_id' => $tipo->id,
        ])
        ->assertStatus(200)
        ->assertJsonPath('datos.tipo_unidad.id', $tipo->id);

    expect($unidad->fresh()->tipo_unidad_id)->toBe($tipo->id);
});
