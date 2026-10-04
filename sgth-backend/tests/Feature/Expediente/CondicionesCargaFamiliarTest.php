<?php

use App\Enums\GradoDiscapacidad;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La discapacidad y la enfermedad catastrófica de una carga familiar.
 *
 * Desde el 2026-10-03: las marcas se derivan de los registros (antes las
 * ponía un interruptor, y apagarlo escondía los registros), los registros se
 * pueden editar (antes solo crear y borrar), y el porcentaje va del 5 al 100,
 * con los grados que fijó Talento Humano, igual que el del servidor.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->uath = User::factory()->create();
    $this->uath->assignRole('admin-uath');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Condiciones']);
    $this->titular = Servidor::forceCreate([
        'cedula' => '0803333331', 'nombre' => 'Pedro', 'apellido' => 'Condición',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Condiciones')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $this->hijo = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0803333332',
        'nombres' => 'Iván', 'apellidos' => 'Condición', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2012-04-01', 'genero' => 'masculino',
    ]);

    $this->base = "/api/v1/expediente/cargas-familiares/{$this->hijo->id}";
    $this->actingAs($this->uath, 'sanctum');
});

test('registrar y borrar una discapacidad enciende y apaga la marca', function () {
    expect($this->hijo->fresh()->persona_con_discapacidad)->toBeFalse();

    $id = $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'intelectual', 'porcentaje' => 45,
    ])->assertCreated()->json('datos.id');

    expect($this->hijo->fresh()->persona_con_discapacidad)->toBeTrue();

    $this->deleteJson("{$this->base}/discapacidades/{$id}")->assertOk();

    expect($this->hijo->fresh()->persona_con_discapacidad)->toBeFalse();
});

test('el formulario del familiar ya no apaga la marca de quien tiene registros', function () {
    $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 30,
    ])->assertCreated();

    $this->putJson("/api/v1/expediente/servidores/{$this->titular->id}/cargas-familiares/{$this->hijo->id}", [
        'cedula' => '0803333332', 'nombres' => 'Iván', 'apellidos' => 'Condición',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2012-04-01', 'genero' => 'masculino',
        'persona_con_discapacidad' => false,
    ])->assertOk();

    expect($this->hijo->fresh()->persona_con_discapacidad)->toBeTrue();
});

test('cada registro solo toca su marca: la de un familiar antiguo sin detalle se conserva', function () {
    // Marcado a mano antes del cambio, sin ningún registro de discapacidad.
    $this->hijo->update(['persona_con_discapacidad' => true]);

    $id = $this->postJson("{$this->base}/enfermedades", ['tipo_enfermedad' => 'Insuficiencia renal crónica'])
        ->assertCreated()->json('datos.id');
    $this->deleteJson("{$this->base}/enfermedades/{$id}")->assertOk();

    expect($this->hijo->fresh())
        ->persona_con_discapacidad->toBeTrue()
        ->posee_enfermedad_catastrofica->toBeFalse();
});

test('la discapacidad y la enfermedad de un familiar se editan', function () {
    $disc = $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 30,
    ])->json('datos.id');
    $enf = $this->postJson("{$this->base}/enfermedades", ['tipo_enfermedad' => 'Cáncer'])
        ->json('datos.id');

    $this->putJson("{$this->base}/discapacidades/{$disc}", [
        'tipo_discapacidad' => 'multiple', 'porcentaje' => 62, 'numero_carnet_conadis' => '13.456',
    ])->assertOk()->assertJsonPath('datos.tipo_discapacidad', 'multiple');

    $this->putJson("{$this->base}/enfermedades/{$enf}", [
        'tipo_enfermedad' => 'Leucemia linfoblástica aguda', 'codigo_cie10' => 'C91.0',
    ])->assertOk()->assertJsonPath('datos.codigo_cie10', 'C91.0');
});

test('no se edita la condición de otro familiar cambiando el id de la URL', function () {
    $otra = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0803333333',
        'nombres' => 'Ana', 'apellidos' => 'Condición', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2016-04-01', 'genero' => 'femenino',
    ]);
    $disc = $this->postJson("/api/v1/expediente/cargas-familiares/{$otra->id}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 30,
    ])->json('datos.id');

    $this->putJson("{$this->base}/discapacidades/{$disc}", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 90,
    ])->assertNotFound();
});

test('el porcentaje de discapacidad va del 5 al 100, en el familiar y en el servidor', function () {
    $mensaje = 'El porcentaje de discapacidad va del 5 % al 100 %.';
    $urlServidor = "/api/v1/expediente/servidores/{$this->titular->id}/discapacidades";

    foreach ([4, 101] as $fuera) {
        $this->postJson("{$this->base}/discapacidades", ['tipo_discapacidad' => 'fisica', 'porcentaje' => $fuera])
            ->assertUnprocessable()->assertJsonPath('errores.porcentaje.0', $mensaje);
        $this->postJson($urlServidor, [
            'tipo_discapacidad' => 'fisica', 'porcentaje' => $fuera, 'numero_carnet_conadis' => '1',
        ])->assertUnprocessable()->assertJsonPath('errores.porcentaje.0', $mensaje);
    }

    $this->postJson("{$this->base}/discapacidades", ['tipo_discapacidad' => 'fisica', 'porcentaje' => 5])
        ->assertCreated();
    $this->postJson($urlServidor, [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 100, 'numero_carnet_conadis' => '1',
    ])->assertCreated();
});

test('un código CIE-10 más largo que la columna da 422, no 500', function () {
    $this->postJson("{$this->base}/enfermedades", [
        'tipo_enfermedad' => 'Cáncer', 'codigo_cie10' => 'C18.0-largo',
    ])->assertUnprocessable()->assertJsonStructure(['errores' => ['codigo_cie10']]);
});

test('el grado se deriva del porcentaje con los rangos de Talento Humano', function () {
    expect(GradoDiscapacidad::desdePorcentaje(4.99))->toBeNull()
        ->and(GradoDiscapacidad::desdePorcentaje(5))->toBe(GradoDiscapacidad::LEVE)
        ->and(GradoDiscapacidad::desdePorcentaje('24.99'))->toBe(GradoDiscapacidad::LEVE)
        ->and(GradoDiscapacidad::desdePorcentaje(25))->toBe(GradoDiscapacidad::MODERADA)
        ->and(GradoDiscapacidad::desdePorcentaje(49))->toBe(GradoDiscapacidad::MODERADA)
        ->and(GradoDiscapacidad::desdePorcentaje(50))->toBe(GradoDiscapacidad::GRAVE)
        ->and(GradoDiscapacidad::desdePorcentaje(74.5))->toBe(GradoDiscapacidad::GRAVE)
        ->and(GradoDiscapacidad::desdePorcentaje(75))->toBe(GradoDiscapacidad::MUY_GRAVE)
        ->and(GradoDiscapacidad::desdePorcentaje(100))->toBe(GradoDiscapacidad::MUY_GRAVE)
        ->and(GradoDiscapacidad::desdePorcentaje(null))->toBeNull();
});

test('la discapacidad de un familiar guarda la caducidad del carné', function () {
    // El request del familiar la descartaba aunque la columna existiera.
    $id = $this->postJson("{$this->base}/discapacidades", [
        'tipo_discapacidad' => 'fisica', 'porcentaje' => 30,
        'numero_carnet_conadis' => '13.456', 'carnet_vencimiento' => '2027-03-31',
    ])->assertCreated()->json('datos.id');

    expect(\App\Models\Expediente\DiscapacidadCargaFamiliar::find($id)->carnet_vencimiento->toDateString())
        ->toBe('2027-03-31');
});
