<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El contacto de emergencia de la ficha (2026-10-04): opcional, con nombre y
 * teléfono juntos, y editable también por el asistente, como el resto del
 * contacto.
 */

beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    $unidad = unidadDePrueba(['nombre' => 'Unidad Emergencia']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0807171711', 'nombre' => 'Elsa', 'apellido' => 'Emergencia',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Emergencia')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->url = "/api/v1/expediente/servidores/{$this->servidor->id}";
});

test('el asistente registra el contacto de emergencia y la ficha lo devuelve', function () {
    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');

    $this->actingAs($asistente, 'sanctum')->putJson($this->url, [
        'contacto_emergencia_nombre'     => 'Rosa Emergencia',
        'contacto_emergencia_parentesco' => 'Madre',
        'contacto_emergencia_telefono'   => '0991234567',
    ])->assertOk();

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('datos.contacto_emergencia_nombre', 'Rosa Emergencia')
        ->assertJsonPath('datos.contacto_emergencia_parentesco', 'Madre')
        ->assertJsonPath('datos.contacto_emergencia_telefono', '0991234567');
});

test('es opcional, pero nombre y teléfono van juntos', function () {
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    // Sin contacto de emergencia: se guarda igual.
    $this->putJson($this->url, ['telefono_celular' => '0990000000'])->assertOk();

    $this->putJson($this->url, ['contacto_emergencia_nombre' => 'Rosa Emergencia'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['contacto_emergencia_telefono']]);

    $this->putJson($this->url, ['contacto_emergencia_telefono' => '0991234567'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['contacto_emergencia_nombre']]);
});

test('corregir solo el nombre no exige reenviar el teléfono ya guardado', function () {
    // El modal de edición envía solo lo que cambió.
    $this->servidor->update([
        'contacto_emergencia_nombre' => 'Rosa', 'contacto_emergencia_telefono' => '0991234567',
    ]);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');

    $this->actingAs($uath, 'sanctum')
        ->putJson($this->url, ['contacto_emergencia_nombre' => 'Rosa Emergencia'])
        ->assertOk();

    // Y borrar el teléfono dejando el nombre, no.
    $this->putJson($this->url, ['contacto_emergencia_telefono' => null])
        ->assertUnprocessable()
        ->assertJsonPath('errores.contacto_emergencia_telefono.0', 'Indique el teléfono del contacto de emergencia.');
});
