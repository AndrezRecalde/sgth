<?php

namespace Tests\Feature\Estructura;

use App\Enums\NivelCompetencia;
use App\Enums\Permiso;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El nivel en que se sugiere cada competencia de un puesto (docs/diseno-manual-
| de-puestos.md, P2; TH, D5, 2026-11-10): sale del grado, en tres tramos, y ya
| no de la complejidad del grupo.
*/
$grupo = fn (string $codigo, ?int $grado, string $regimen = 'losep') => new GrupoOcupacional([
    'grado_codigo' => $codigo, 'grado_numerico' => $grado, 'regimen' => $regimen,
]);

test('hasta el grado 7, bajo; del 8 al 13, medio; del 14 en adelante, alto', function () use ($grupo) {
    expect($grupo('SPA1', 3)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::BAJO)
        ->and($grupo('SP1', 7)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::BAJO)
        ->and($grupo('SP2', 8)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::MEDIO)
        // SP6 y SP7 tenían complejidad alta, pero el manual los pone en medio.
        ->and($grupo('SP7', 13)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::MEDIO)
        ->and($grupo('SP8', 14)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::ALTO);
});

test('el Nivel Jerárquico Superior va en alto aunque numere sus grados aparte, y el obrero no tiene', function () use ($grupo) {
    expect($grupo('NJS-5', 5)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::ALTO)
        ->and($grupo('NJS-1', 1)->nivelDeCompetenciaSugerido())->toBe(NivelCompetencia::ALTO)
        ->and($grupo('CT', null, 'codigo_trabajo')->nivelDeCompetenciaSugerido())->toBeNull();
});

test('el listado de puestos lo trae con el grupo ocupacional', function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum']));
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    // `puestoDePrueba` usa SP10, grado 10.
    $puesto = puestoDePrueba(unidadDePrueba(['nombre' => 'Dirección del Nivel']), 'Analista del Nivel');

    $fila = collect($this->actingAs($usuario, 'sanctum')->getJson('/api/v1/estructura/puestos?per_page=50')->assertOk()->json('datos'))
        ->firstWhere('id', $puesto->id);

    expect($fila['grupo_ocupacional']['nivel_competencia_sugerido'])->toBe('medio');
});
