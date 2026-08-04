<?php

namespace Tests\Feature\Evaluacion;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Evaluacion\EvaluacionDesempeno;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->evaluador = User::factory()->create();
    $this->actingAs($this->evaluador, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->evaluacion = EvaluacionDesempeno::create([
        'periodo'      => '2026-S1',
        'fecha_inicio' => '2026-01-01',
        'fecha_fin'    => '2026-06-30',
        'estado'       => 'en_evaluacion',
        'created_by'   => $this->evaluador->id,
    ]);
});

function crearServidorConVinculo(string $tipoNombramiento): Servidor
{
    /** @var UnidadAdministrativa $unidad */
    $unidad = test()->unidad;
    /** @var Puesto $puesto */
    $puesto = test()->puesto;

    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula'  => (string) random_int(1000000000, 1999999999),
        'nombre'  => 'Servidor', 'apellido' => $tipoNombramiento,
        'regimen_laboral' => 'losep',
        'puesto_id' => $puesto->id,
        'unidad_administrativa_id' => $unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => $tipoNombramiento,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2020-01-01',
        // Servicios Profesionales dura el año calendario y la BD exige el
        // vencimiento; el resto de nombramientos no lleva plazo.
        'fecha_fin'                => $tipoNombramiento === 'servicios_profesionales'
            ? '2020-12-31'
            : null,
        'estado'                   => 'vigente',
    ]);

    return $servidor;
}

test('registrar resultado falla con 422 para un servidor con vínculo de servicios profesionales', function () {
    $servidor = crearServidorConVinculo('servicios_profesionales');

    $response = $this->postJson(
        "/api/v1/evaluacion/evaluaciones/{$this->evaluacion->id}/servidor/{$servidor->id}",
        ['calificacion_cuantitativa' => 95]
    );

    $response->assertStatus(422);
    expect($response->json('mensaje'))->toBe('Evaluación de desempeño no aplica a este régimen contractual.');
});

test('registrar resultado falla con 422 para un servidor con vínculo de código de trabajo', function () {
    $servidor = crearServidorConVinculo('codigo_trabajo');

    $response = $this->postJson(
        "/api/v1/evaluacion/evaluaciones/{$this->evaluacion->id}/servidor/{$servidor->id}",
        ['calificacion_cuantitativa' => 95]
    );

    $response->assertStatus(422);
    expect($response->json('mensaje'))->toBe('Evaluación de desempeño no aplica a este régimen contractual.');
});

test('registrar resultado falla con 422 para un servidor sin ningún vínculo vigente', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula'  => '1234567890', 'nombre' => 'Sin', 'apellido' => 'Vinculo',
        'regimen_laboral' => 'losep',
    ]);

    $response = $this->postJson(
        "/api/v1/evaluacion/evaluaciones/{$this->evaluacion->id}/servidor/{$servidor->id}",
        ['calificacion_cuantitativa' => 95]
    );

    $response->assertStatus(422);
});

test('registrar resultado funciona igual que antes para un servidor con nombramiento LOSEP', function () {
    $servidor = crearServidorConVinculo('nombramiento_permanente');

    $response = $this->postJson(
        "/api/v1/evaluacion/evaluaciones/{$this->evaluacion->id}/servidor/{$servidor->id}",
        ['calificacion_cuantitativa' => 95]
    );

    $response->assertStatus(200);
    expect($response->json('mensaje'))->toContain('EXCELENTE');

    $this->assertDatabaseHas('resultados_evaluacion', [
        'evaluacion_id' => $this->evaluacion->id,
        'servidor_id'   => $servidor->id,
    ]);
});
