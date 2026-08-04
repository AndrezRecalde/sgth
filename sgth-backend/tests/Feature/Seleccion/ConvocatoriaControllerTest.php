<?php

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');

    $unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id,
        'plazas' => 5,
        'regimen_laboral' => 'losep',
        'activo' => true,
    ]);

    $this->payloadBase = [
        'puesto_id'   => $this->puesto->id,
        'titulo'      => 'Analista de Talento Humano',
        'descripcion' => 'Concurso de méritos y oposición',
        'tipo'        => 'externa',
        'vacantes'    => 1,
    ];
});

test('formal: crea la convocatoria con fecha_inicio/fecha_fin obligatorias', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso' => 'formal',
        'fecha_inicio' => '2026-08-01',
        'fecha_fin'    => '2026-08-15',
    ]);

    $response->assertStatus(201);
    expect($response->json('datos.tipo_proceso'))->toBe('formal');
    expect($response->json('datos.tipo_nombramiento_previsto'))->toBeNull();
    expect($response->json('datos.fecha_inicio'))->toContain('2026-08-01');
    expect($response->json('datos.fecha_fin'))->toContain('2026-08-15');
});

test('formal: sin fecha_inicio ni fecha_fin responde 422, no 500', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso' => 'formal',
    ]);

    $response->assertStatus(422);
    expect($response->json('errores'))->toHaveKeys(['fecha_inicio', 'fecha_fin']);
});

test('formal: si igual envían tipo_nombramiento_previsto, responde 422 (no 500 por el CHECK de la BD)', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso'    => 'formal',
        'fecha_inicio'    => '2026-08-01',
        'fecha_fin'       => '2026-08-15',
        'tipo_nombramiento_previsto' => 'servicios_ocasionales',
    ]);

    $response->assertStatus(422);
    expect($response->json('errores'))->toHaveKey('tipo_nombramiento_previsto');
    expect(Convocatoria::count())->toBe(0);
});

test('express: crea la convocatoria sin fechas, autocompletadas con hoy, con el tipo_nombramiento_previsto declarado', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso' => 'express',
        'tipo_nombramiento_previsto' => 'servicios_ocasionales',
    ]);

    $response->assertStatus(201);
    expect($response->json('datos.tipo_proceso'))->toBe('express');
    expect($response->json('datos.tipo_nombramiento_previsto'))->toBe('servicios_ocasionales');
    expect($response->json('datos.fecha_inicio'))->toContain(now()->toDateString());
    expect($response->json('datos.fecha_fin'))->toContain(now()->toDateString());
});

test('express: sin tipo_nombramiento_previsto responde 422, no 500', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso' => 'express',
    ]);

    $response->assertStatus(422);
    expect($response->json('errores'))->toHaveKey('tipo_nombramiento_previsto');
});

test('express: tipo_nombramiento_previsto fuera de los 4 valores permitidos responde 422', function () {
    // 'nombramiento_permanente' es válido en TipoNombramiento pero NO está
    // entre los 4 permitidos para express (confirmado con Talento Humano).
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'tipo_proceso' => 'express',
        'tipo_nombramiento_previsto' => 'nombramiento_permanente',
    ]);

    $response->assertStatus(422);
    expect($response->json('errores'))->toHaveKey('tipo_nombramiento_previsto');
});

test('sin tipo_proceso responde 422, no 500', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/seleccion/convocatorias', [
        ...$this->payloadBase,
        'fecha_inicio' => '2026-08-01',
        'fecha_fin'    => '2026-08-15',
    ]);

    $response->assertStatus(422);
    expect($response->json('errores'))->toHaveKey('tipo_proceso');
});

test('publicar sin criterios responde 422 con mensaje claro', function () {
    $convocatoria = Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-TEST-001',
        'titulo' => 'Analista', 'descripcion' => 'x', 'tipo' => 'externa', 'vacantes' => 1,
        'fecha_inicio' => now(), 'fecha_fin' => now()->addDays(10),
        'estado' => 'borrador', 'tipo_proceso' => 'formal',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/publicar");

    $response->assertStatus(422);
    expect($response->json('mensaje'))->toContain('sin criterios de evaluación configurados');
    expect($convocatoria->fresh()->estado->value)->toBe('borrador');
});

test('publicar con al menos un criterio funciona igual que hoy', function () {
    $convocatoria = Convocatoria::create([
        'puesto_id' => $this->puesto->id, 'codigo' => 'CNV-TEST-002',
        'titulo' => 'Analista', 'descripcion' => 'x', 'tipo' => 'externa', 'vacantes' => 1,
        'fecha_inicio' => now(), 'fecha_fin' => now()->addDays(10),
        'estado' => 'borrador', 'tipo_proceso' => 'formal',
    ]);

    CriterioEvaluacion::create([
        'convocatoria_id' => $convocatoria->id, 'seccion' => 'meritos',
        'nombre' => 'Instrucción formal', 'puntaje_maximo' => 100,
        'tipo_input' => 'numero', 'orden' => 1, 'activo' => true,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/publicar");

    $response->assertStatus(200);
    expect($convocatoria->fresh()->estado->value)->toBe('publicada');
});
