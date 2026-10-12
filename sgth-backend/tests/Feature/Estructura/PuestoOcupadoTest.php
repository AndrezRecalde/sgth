<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.1a del diseño de Acciones de Personal (8.4 y 9.7): lo que Estructura
| ya no hace a mano con un puesto ocupado. La unidad cambia con el traspaso,
| que emite el acto de cada ocupante; el régimen no cambia, no se desactiva,
| las plazas no bajan de las ocupadas, y lo que tiene historia no se borra.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    foreach ([Permiso::GESTIONAR_PUESTOS, Permiso::VER_ESTRUCTURA] as $permiso) {
        $rol->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }

    $this->user = User::factory()->create();
    $this->user->assignRole($rol);
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->otra = unidadDePrueba(['nombre' => 'Dirección de Planificación']);

    $this->ocupado = puestoDePrueba($this->unidad, 'Analista de Proyectos', ['plazas' => 2]);
    $servidor = Servidor::create([
        'cedula' => '0912345670', 'nombre' => 'Servidor', 'apellido' => 'Ocupante', 'regimen_laboral' => 'losep',
    ]);
    ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->ocupado->id,
        'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
    ]);

    $this->editar = fn (Puesto $p, array $datos) => $this->putJson("/api/v1/estructura/puestos/{$p->id}", $datos);
});

test('ocupado, no cambia de unidad ni de régimen, no se desactiva y no baja de las plazas ocupadas', function () {
    ($this->editar)($this->ocupado, ['unidad_administrativa_id' => $this->otra->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'traspaso de puesto'));

    ($this->editar)($this->ocupado, ['regimen_laboral' => 'codigo_trabajo', 'grupo_ocupacional_id' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'régimen laboral'));

    ($this->editar)($this->ocupado, ['activo' => false])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no se desactiva'));

    ($this->editar)($this->ocupado, ['plazas' => 0])->assertStatus(422);

    expect($this->ocupado->fresh()->unidad_administrativa_id)->toBe($this->unidad->id);

    // Lo demás se sigue editando.
    ($this->editar)($this->ocupado, ['plazas' => 1, 'nivel_complejidad' => 'alto'])->assertOk();
});

test('vacante, cambia de unidad, y el registro guarda el antes, el después y la causa', function () {
    $vacante = puestoDePrueba($this->unidad, 'Asistente');

    ($this->editar)($vacante, ['unidad_administrativa_id' => $this->otra->id])->assertOk();

    $registro = Activity::query()->where('subject_type', Puesto::class)->where('subject_id', $vacante->id)
        ->where('event', 'updated')->latest('id')->first();

    expect($vacante->fresh()->unidad_administrativa_id)->toBe($this->otra->id)
        ->and($registro->properties['antes']['unidad_administrativa_id'])->toBe($this->unidad->id)
        ->and($registro->properties['despues']['unidad_administrativa_id'])->toBe($this->otra->id)
        ->and($registro->properties['causa'])->toBe('Edición directa en Estructura');
});

test('una jefatura no se lleva a una unidad que ya tiene la suya', function () {
    puestoJefeDePrueba($this->otra);
    $jefatura = puestoJefeDePrueba($this->unidad);

    ($this->editar)($jefatura, ['unidad_administrativa_id' => $this->otra->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'conducción'));
});

test('no se elimina el ocupado ni el que tiene historia; el que nunca se usó, sí', function () {
    $this->deleteJson("/api/v1/estructura/puestos/{$this->ocupado->id}")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ocupado'));

    // Con historia: un contrato ya cerrado.
    $conHistoria = puestoDePrueba($this->unidad, 'Técnico');
    ContratoServidor::create([
        'servidor_id' => Servidor::create(['cedula' => '0912345671', 'nombre' => 'Ex', 'apellido' => 'Servidor', 'regimen_laboral' => 'losep'])->id,
        'tipo_nombramiento' => 'nombramiento_permanente', 'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $conHistoria->id, 'fecha_inicio' => '2015-01-05', 'fecha_fin' => '2020-01-05', 'estado' => 'terminado',
    ]);

    $this->deleteJson("/api/v1/estructura/puestos/{$conHistoria->id}")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Desactívelo'));

    $nuevo = puestoDePrueba($this->unidad, 'Creado por error');
    $this->deleteJson("/api/v1/estructura/puestos/{$nuevo->id}")->assertOk();

    expect(Puesto::find($nuevo->id))->toBeNull()
        ->and(Puesto::find($conHistoria->id))->not->toBeNull();
});

test('el listado dice si está ocupado, si se puede eliminar y si admite el traspaso', function () {
    $vacante = puestoDePrueba($this->unidad, 'Asistente');
    $jefatura = puestoJefeDePrueba($this->unidad);
    $prefecto = puestoDePrueba($this->otra, 'Prefecto/a Provincial', ['rol_puesto' => 'dignatario']);

    $filas = collect($this->getJson('/api/v1/estructura/puestos?per_page=50')->assertOk()->json('datos'))->keyBy('id');

    expect($filas[$this->ocupado->id]['ocupado'])->toBeTrue()
        ->and($filas[$this->ocupado->id]['puede_eliminar'])->toBeFalse()
        ->and($filas[$this->ocupado->id]['admite_traspaso'])->toBeTrue()
        ->and($filas[$vacante->id]['ocupado'])->toBeFalse()
        ->and($filas[$vacante->id]['puede_eliminar'])->toBeTrue()
        // Ni la jefatura ni el puesto de una autoridad se traspasan.
        ->and($filas[$jefatura->id]['admite_traspaso'])->toBeFalse()
        ->and($filas[$prefecto->id]['admite_traspaso'])->toBeFalse();
});

// ── El grado y la denominación (fase 7.1b) ──────────────────────

test('ocupado, no cambia de grado ni de denominación a mano: va con la revisión de la clasificación', function () {
    $otroGrupo = GrupoOcupacional::firstOrCreate(['grado_codigo' => 'SP12'], [
        'grado_numerico' => 12, 'grupo' => 'Profesional', 'denominacion_generica' => 'Profesional',
        'rmu' => 1200, 'regimen' => 'losep', 'activo' => true,
    ]);

    ($this->editar)($this->ocupado, ['grupo_ocupacional_id' => $otroGrupo->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su grado cambia con la revisión de la clasificación'));

    ($this->editar)($this->ocupado, ['cargo_id' => Cargo::firstOrCreate(['nombre' => 'Especialista'])->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su denominación cambia'));

    // El vacante, sí.
    $vacante = puestoDePrueba($this->unidad, 'Asistente');
    ($this->editar)($vacante, ['grupo_ocupacional_id' => $otroGrupo->id])->assertOk();
});

test('el cargo con puestos ocupados se corrige con el motivo, y queda registrado', function () {
    $cargo = $this->ocupado->cargo;

    $this->getJson('/api/v1/estructura/cargos')
        ->assertOk()
        ->assertJsonPath('datos', fn (array $cargos) => collect($cargos)->firstWhere('id', $cargo->id)['con_puestos_ocupados'] === true);

    $this->putJson("/api/v1/estructura/cargos/{$cargo->id}", ['nombre' => 'Analista de Proyectos y Obras'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['motivo_correccion'], 'errores');

    $this->putJson("/api/v1/estructura/cargos/{$cargo->id}", [
        'nombre' => 'Analista de Proyectos y Obras', 'motivo_correccion' => 'Faltaba «y Obras» en el nombre.',
    ])->assertOk();

    $registro = Activity::query()->where('subject_type', Cargo::class)->where('subject_id', $cargo->id)
        ->latest('id')->first();

    expect($cargo->fresh()->nombre)->toBe('Analista de Proyectos y Obras')
        ->and($registro->properties['antes']['nombre'])->toBe('Analista de Proyectos')
        ->and($registro->properties['causa'])->toBe('Faltaba «y Obras» en el nombre.');

    // El cargo sin puestos ocupados se renombra como siempre.
    $libre = Cargo::firstOrCreate(['nombre' => 'Sin ocupantes']);
    $this->putJson("/api/v1/estructura/cargos/{$libre->id}", ['nombre' => 'Sin ocupantes (corregido)'])->assertOk();
});
