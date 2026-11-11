<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoPerfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La descripción del puesto, como la ficha del Manual de Puestos
| (docs/diseno-manual-de-puestos.md, P1): misión, relaciones, requisitos de
| instrucción y experiencia, capacitación y subproceso. La misión es de cada
| puesto (TH, D1) y el subproceso, un texto del perfil (D2).
*/
beforeEach(function () {
    $permiso = fn (Permiso $p) => Permission::firstOrCreate(['name' => $p->value, 'guard_name' => 'sanctum']);

    $gestor = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $gestor->givePermissionTo([$permiso(Permiso::VER_ESTRUCTURA), $permiso(Permiso::GESTIONAR_PUESTOS)]);
    $this->gestor = User::factory()->create();
    $this->gestor->assignRole($gestor);

    $lector = Role::firstOrCreate(['name' => 'lector-estructura-prueba', 'guard_name' => 'sanctum']);
    $lector->givePermissionTo($permiso(Permiso::VER_ESTRUCTURA));
    $this->lector = User::factory()->create();
    $this->lector->assignRole($lector);

    $this->puesto = puestoDePrueba(unidadDePrueba(['nombre' => 'Dirección del Perfil']), 'Analista de Soporte');
    $this->url = "/api/v1/estructura/puestos/{$this->puesto->id}/perfil";
});

test('un puesto sin perfil devuelve uno vacío', function () {
    $this->actingAs($this->gestor, 'sanctum')->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('datos.puesto_id', $this->puesto->id)
        ->assertJsonPath('datos.mision', null)
        ->assertJsonPath('datos.requisitos', []);

    expect(PuestoPerfil::count())->toBe(0);
});

test('guardar crea el perfil con sus alternativas en orden, propone el nivel y deja bitácora', function () {
    $this->actingAs($this->gestor, 'sanctum')->putJson($this->url, [
        'mision'     => 'Brindar soporte técnico a los usuarios de la institución.',
        'relaciones' => 'Internas: todas las unidades. Externas: proveedores.',
        'subproceso' => 'Soporte Técnico',
        'requisitos' => [
            ['nivel_instruccion' => 'tecnico_superior', 'meses_experiencia' => 72],
            ['nivel_instruccion' => 'tecnologico_superior', 'meses_experiencia' => 60],
            ['nivel_instruccion' => 'tercer_nivel', 'meses_experiencia' => 30],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('datos.subproceso', 'Soporte Técnico')
        // Una alternativa pide tercer nivel: profesional.
        ->assertJsonPath('datos.nivel', 'profesional')
        ->assertJsonPath('datos.requisitos.0.nivel_instruccion', 'tecnico_superior')
        ->assertJsonPath('datos.requisitos.0.nivel_instruccion_label', 'Técnico superior')
        ->assertJsonPath('datos.requisitos.2.meses_experiencia', 30);

    $registro = Activity::where('subject_type', Puesto::class)->where('subject_id', $this->puesto->id)
        ->where('description', 'Perfil del puesto actualizado')->sole();
    expect($registro->properties['despues']['subproceso'])->toBe('Soporte Técnico');
});

test('guardar otra vez reemplaza las alternativas, respeta el nivel elegido y deja lo que no llega', function () {
    $this->actingAs($this->gestor, 'sanctum');
    $this->putJson($this->url, [
        'mision'     => 'Misión original.',
        'requisitos' => [['nivel_instruccion' => 'tercer_nivel', 'meses_experiencia' => 12]],
    ])->assertOk();

    $this->putJson($this->url, [
        'nivel'      => 'no_profesional',
        'requisitos' => [['nivel_instruccion' => 'bachiller', 'meses_experiencia' => 24]],
    ])
        ->assertOk()
        ->assertJsonPath('datos.mision', 'Misión original.')
        ->assertJsonPath('datos.nivel', 'no_profesional')
        ->assertJsonCount(1, 'datos.requisitos')
        ->assertJsonPath('datos.requisitos.0.nivel_instruccion', 'bachiller');
});

test('los estudios en curso piden qué está aprobado, y el nivel tiene que existir', function () {
    $this->actingAs($this->gestor, 'sanctum');

    $this->putJson($this->url, ['requisitos' => [['nivel_instruccion' => 'estudios_superiores_en_curso', 'meses_experiencia' => 0]]])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['requisitos.0.detalle']]);

    $this->putJson($this->url, ['requisitos' => [['nivel_instruccion' => 'doctorado', 'meses_experiencia' => 0]]])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['requisitos.0.nivel_instruccion']]);

    $this->putJson($this->url, ['requisitos' => [[
        'nivel_instruccion' => 'estudios_superiores_en_curso', 'detalle' => 'Tercer año o sexto semestre aprobado', 'meses_experiencia' => 0,
    ]]])->assertOk()->assertJsonPath('datos.requisitos.0.detalle', 'Tercer año o sexto semestre aprobado');
});

test('lo lee quien ve la estructura y lo escribe quien gestiona puestos', function () {
    $this->actingAs($this->lector, 'sanctum')->getJson($this->url)->assertOk();
    // Un 403, no un 422, aunque los datos no valgan.
    $this->actingAs($this->lector, 'sanctum')->putJson($this->url, ['nivel' => 'otro'])->assertForbidden();

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson($this->url)->assertForbidden();
});

test('la migración pasa la misión de cada cargo al perfil de sus puestos', function () {
    $otro = puestoDePrueba($this->puesto->unidadAdministrativa, 'Analista de Soporte', ['plazas' => 2]);
    $migracion = require database_path('migrations/2026_12_25_000000_perfil_del_puesto.php');

    $migracion->down();
    DB::table('cargos')->where('id', $this->puesto->cargo_id)->update(['mision' => 'Dar soporte técnico.']);
    $migracion->up();

    expect(PuestoPerfil::whereIn('puesto_id', [$this->puesto->id, $otro->id])->pluck('mision')->unique()->all())
        ->toBe(['Dar soporte técnico.'])
        ->and(Schema::hasColumn('cargos', 'mision'))->toBeFalse();
});
