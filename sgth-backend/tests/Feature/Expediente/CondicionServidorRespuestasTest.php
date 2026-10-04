<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Las rutas de discapacidad y enfermedad del servidor (2026-10-03):
 * responden con ApiResponse como el resto, un servidor inexistente da 404 y
 * no 500, el `show` que nadie usaba ya no está, y la ruta interna del archivo
 * no viaja al navegador.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Respuestas']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0800808081', 'nombre' => 'Iris', 'apellido' => 'Respuesta',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Respuestas')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->base = "/api/v1/expediente/servidores/{$this->servidor->id}";
});

test('el alta responde con datos y sin la ruta interna del archivo', function () {
    $this->servidor->discapacidades()->create([
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 40, 'numero_carnet_conadis' => '1',
        'carnet_ruta' => 'expediente/discapacidades/secreto.pdf',
    ]);

    $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 40, 'numero_carnet_conadis' => '2',
    ])->assertCreated()->assertJsonPath('datos.porcentaje', '40.00');

    $this->postJson("{$this->base}/enfermedades", ['tipo_enfermedad' => 'Cáncer'])
        ->assertCreated()->assertJsonPath('datos.tipo_enfermedad', 'Cáncer');

    $this->getJson("{$this->base}/discapacidades")
        ->assertOk()
        ->assertJsonMissingPath('datos.0.carnet_ruta');
});

test('un servidor inexistente da 404, no 500', function () {
    $this->postJson('/api/v1/expediente/servidores/999999/discapacidades', [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 40, 'numero_carnet_conadis' => '1',
    ])->assertNotFound();
});

test('el show de un registro suelto ya no existe', function () {
    $id = $this->servidor->discapacidades()->create([
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 40, 'numero_carnet_conadis' => '1',
    ])->id;

    $this->getJson("{$this->base}/discapacidades/{$id}")->assertStatus(405);
});
