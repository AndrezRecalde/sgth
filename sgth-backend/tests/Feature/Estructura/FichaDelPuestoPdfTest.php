<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\User;
use App\Services\Estructura\FichaDelPuestoPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La ficha «Descripción y perfil del puesto» en PDF, con las diez secciones del
| Manual de Puestos (docs/diseno-manual-de-puestos.md, P5). Imprime lo que el
| puesto tenga cargado.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'lector-ficha-prueba', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum']));
    $this->lector = User::factory()->create();
    $this->lector->assignRole($rol);

    $this->puesto = puestoDePrueba(unidadDePrueba(['nombre' => 'Gestión de la Ficha']), 'Analista de Soporte');
    $this->puesto->perfil()->create([
        'mision'     => 'Brindar soporte técnico a los usuarios.',
        'subproceso' => 'Soporte Técnico',
        'nivel'      => 'profesional',
    ])->requisitos()->createMany([
        ['nivel_instruccion' => 'tecnico_superior', 'meses_experiencia' => 72, 'orden' => 1],
        ['nivel_instruccion' => 'tercer_nivel', 'meses_experiencia' => 30, 'orden' => 2],
    ]);
    PuestoActividad::create([
        'puesto_id' => $this->puesto->id, 'descripcion' => 'Atender los requerimientos de soporte',
        'conocimientos' => 'Redes y sistemas operativos', 'orden' => 1, 'activo' => true,
    ]);
    $this->tecnica = Competencia::where('tipo', 'tecnica')->orderBy('numero')->firstOrFail();
    $this->conductual = Competencia::where('tipo', 'conductual')->orderBy('numero')->firstOrFail();
    PuestoCompetencia::create(['puesto_id' => $this->puesto->id, 'competencia_id' => $this->tecnica->id, 'nivel' => 'medio']);
    PuestoCompetencia::create(['puesto_id' => $this->puesto->id, 'competencia_id' => $this->conductual->id, 'nivel' => 'alto']);
});

test('la ficha trae las diez secciones con lo que el puesto tiene cargado', function () {
    $datos = app(FichaDelPuestoPdfService::class)->datos($this->puesto->fresh());

    expect($datos['identificacion']['Código'])->toBeNull()
        ->and($datos['identificacion']['Denominación del puesto'])->toBe('Analista de Soporte')
        ->and($datos['identificacion']['Nivel'])->toBe('Profesional')
        // «Gestión / Subproceso», como el manual (D2).
        ->and($datos['identificacion']['Unidad administrativa'])->toBe('Gestión de la Ficha / Soporte Técnico')
        ->and($datos['identificacion']['Ámbito'])->toBe('Provincial')
        ->and($datos['mision'])->toBe('Brindar soporte técnico a los usuarios.')
        ->and($datos['instruccion'])->toBe([
            ['nivel' => 'Técnico superior', 'experiencia' => '6 años'],
            ['nivel' => 'Tercer nivel de grado', 'experiencia' => '2 años y 6 meses'],
        ])
        ->and($datos['actividades'][0]['conocimientos'])->toBe('Redes y sistemas operativos')
        ->and($datos['tecnicas'])->toHaveCount(1)
        ->and($datos['tecnicas'][0]['nivel'])->toBe('Medio')
        ->and($datos['tecnicas'][0]['numero'])->toBe($this->tecnica->numeroDeComportamiento(\App\Enums\NivelCompetencia::MEDIO))
        ->and($datos['conductuales'][0]['comportamiento'])->toBe($this->conductual->comportamiento_alto);

    $html = view('pdf.estructura.ficha-puesto', $datos)->render();
    expect($html)->toContain('DESCRIPCIÓN Y PERFIL DEL PUESTO')
        ->toContain('10. COMPETENCIAS CONDUCTUALES')
        ->toContain('Atender los requerimientos de soporte')
        // El nombre del formato, sin su código.
        ->not->toContain('MRL');
});

test('la descarga la tiene quien ve la estructura, y es un PDF', function () {
    $url = "/api/v1/estructura/puestos/{$this->puesto->id}/perfil/pdf";

    $respuesta = $this->actingAs($this->lector, 'sanctum')->get($url)->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('ficha_puesto_analista_de_soporte_')
        ->and(substr($respuesta->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->create(), 'sanctum')->get($url)->assertForbidden();
});

test('la experiencia se escribe como en el manual', function () {
    expect(FichaDelPuestoPdfService::textoExperiencia(0))->toBe('Sin experiencia')
        ->and(FichaDelPuestoPdfService::textoExperiencia(1))->toBe('1 mes')
        ->and(FichaDelPuestoPdfService::textoExperiencia(12))->toBe('1 año')
        ->and(FichaDelPuestoPdfService::textoExperiencia(30))->toBe('2 años y 6 meses');
});
