<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Las marcas `tiene_discapacidad` y `tiene_enfermedad_catastrofica` se derivan
| de los registros del expediente (pestaña Condición). Hasta el 2026-09-20
| eran además dos interruptores del formulario de la ficha, así que podían
| decir que sí sin ningún caso registrado —y al revés, ocultar los que había—.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['codigo' => 'UATH', 'nombre' => 'Talento Humano']);
    $this->servidor = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Ana', 'apellido' => 'Prueba',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
    ]);
    $this->base = "/api/v1/expediente/servidores/{$this->servidor->id}";
});

test('registrar una discapacidad enciende la marca y borrarla la apaga', function () {
    // Una ficha recién creada no tiene la marca puesta (la columna admite nulo).
    expect($this->servidor->tiene_discapacidad)->toBeFalsy();

    $id = $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'fisica',
        'porcentaje' => 45,
        'numero_carnet_conadis' => 'C-123',
    ])->assertCreated()->json('data.id');

    expect($this->servidor->fresh()->tiene_discapacidad)->toBeTrue();

    $this->deleteJson("{$this->base}/discapacidades/{$id}")->assertOk();

    expect($this->servidor->fresh()->tiene_discapacidad)->toBeFalse();
});

test('registrar una enfermedad catastrófica enciende su marca y borrarla la apaga', function () {
    $id = $this->postJson("{$this->base}/enfermedades", [
        'tipo_enfermedad' => 'Insuficiencia renal',
        'codigo_cie10' => 'N18',
    ])->assertCreated()->json('data.id');

    expect($this->servidor->fresh()->tiene_enfermedad_catastrofica)->toBeTrue();

    $this->deleteJson("{$this->base}/enfermedades/{$id}")->assertOk();

    expect($this->servidor->fresh()->tiene_enfermedad_catastrofica)->toBeFalse();
});

test('la ficha no puede marcar a mano una condición que no tiene registros', function () {
    $this->putJson($this->base, [
        'tiene_discapacidad' => true,
        'tiene_enfermedad_catastrofica' => true,
    ])->assertUnprocessable()
        ->assertJsonPath(
            'errores.tiene_discapacidad.0',
            'La discapacidad se registra en la pestaña Condición del expediente.',
        );

    expect($this->servidor->fresh()->tiene_discapacidad)->toBeFalsy();
});

test('registrar una ficha nueva tampoco acepta las marcas', function () {
    $this->postJson('/api/v1/expediente/servidores/basico', [
        'cedula' => '2222222222', 'nombre' => 'Nueva', 'apellido' => 'Ficha',
        'fecha_nacimiento' => '1990-05-05', 'genero' => 'femenino',
        'estado_civil' => 'soltero', 'es_extranjero' => true,
        'nacionalidad' => 'Colombiana', 'pais_origen' => 'Colombia',
        'tiene_discapacidad' => true,
        'tiene_enfermedad_catastrofica' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('tiene_discapacidad', 'errores');
});

test('una ficha nueva sin las marcas se registra y nace sin condiciones', function () {
    $id = $this->postJson('/api/v1/expediente/servidores/basico', [
        'cedula' => '3333333333', 'nombre' => 'Nueva', 'apellido' => 'Ficha',
        'fecha_nacimiento' => '1990-05-05', 'genero' => 'femenino',
        'estado_civil' => 'soltero', 'es_extranjero' => true,
        'nacionalidad' => 'Colombiana', 'pais_origen' => 'Colombia',
    ])->assertCreated()->json('datos.id');

    $ficha = Servidor::find($id);
    expect($ficha->tiene_discapacidad)->toBeFalsy()
        ->and($ficha->tiene_enfermedad_catastrofica)->toBeFalsy();
});
