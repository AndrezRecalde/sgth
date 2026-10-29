<?php

use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\RiesgoExamen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El mapa riesgo → exámenes (fase 5 de docs/diseno-examenes-medicos.md, D6): lo
| arma el médico ocupacional o la administración, por factor del catálogo
| oficial del MSP, y nunca con exámenes sensibles.
*/

function usuarioDeRiesgos(string $rol): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $u;
}

function idsDeExamenesPorRiesgo(array $nombres): array
{
    return array_map(fn ($n) => CatalogoExamen::where('nombre', $n)->value('id'), $nombres);
}

function guardarRiesgo(User $quien, string $categoria, string $factor, array $examenes): Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->putJson('/api/v1/dispensario/fichas-sso/examenes-por-riesgo', [
        'categoria' => $categoria, 'factor' => $factor, 'examenes' => idsDeExamenesPorRiesgo($examenes),
    ]);
}

test('el_medico_asigna_los_examenes_de_un_factor_y_se_reemplazan', function () {
    $medico = usuarioDeRiesgos('medico');
    $medico->assignRole('medico-ocupacional');

    guardarRiesgo($medico, 'fisico', 'Ruido', ['Audiometría tonal'])->assertOk();
    guardarRiesgo($medico, 'quimico', 'Polvos', ['Espirometría', 'Rx estándar de tórax'])->assertOk();
    // Volver a guardar reemplaza: no suma.
    guardarRiesgo($medico, 'quimico', 'Polvos', ['Espirometría'])
        ->assertOk()
        ->assertJsonPath('datos.examenes.0.nombre', 'Espirometría');

    $mapa = collect($this->getJson('/api/v1/dispensario/fichas-sso/examenes-por-riesgo')->assertOk()->json('datos'))
        ->mapWithKeys(fn ($f) => ["{$f['categoria']}|{$f['factor']}" => collect($f['examenes'])->pluck('nombre')->all()]);

    expect($mapa->all())->toBe([
        'fisico|Ruido'   => ['Audiometría tonal'],
        'quimico|Polvos' => ['Espirometría'],
    ]);

    // Una lista vacía deja al factor sin exámenes.
    guardarRiesgo($medico, 'fisico', 'Ruido', [])->assertOk();
    expect(RiesgoExamen::where('factor', 'Ruido')->count())->toBe(0);
});

test('solo_factores_del_catalogo_del_msp_y_nunca_examenes_sensibles', function () {
    $medico = usuarioDeRiesgos('medico');
    $medico->assignRole('medico-ocupacional');

    guardarRiesgo($medico, 'fisico', 'Trabajo en alturas', ['Audiometría tonal'])
        ->assertStatus(422)
        ->assertJsonPath('errores.factor.0', 'Ese factor no es de la categoría indicada en el formulario del MSP.');

    guardarRiesgo($medico, 'quimico', 'Ruido', ['Audiometría tonal'])->assertStatus(422);

    guardarRiesgo($medico, 'biologico', 'Virus', ['HIV 1/2/0'])
        ->assertStatus(422)
        ->assertJsonPath('errores.examenes.0', 'Un factor de riesgo no puede pedir exámenes sensibles: quite HIV 1/2/0.');

    expect(RiesgoExamen::count())->toBe(0);
});

test('lo_arman_el_medico_y_la_administracion', function () {
    guardarRiesgo(usuarioDeRiesgos('admin-dispensario'), 'fisico', 'Ruido', ['Audiometría tonal'])->assertOk();
    guardarRiesgo(usuarioDeRiesgos('enfermera'), 'fisico', 'Ruido', [])->assertForbidden();
    guardarRiesgo(usuarioDeRiesgos('odontologo'), 'fisico', 'Ruido', [])->assertForbidden();
});
