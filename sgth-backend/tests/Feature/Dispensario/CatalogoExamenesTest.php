<?php

use App\Models\Dispensario\CatalogoExamen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * El catálogo de exámenes (fase 1 de docs/diseno-examenes-medicos.md): la hoja
 * del laboratorio que usaban los médicos, cargada por la migración; lo lee el
 * personal clínico y lo mantiene la administración del Dispensario.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, ?string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        if ($rol) {
            $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));
        }

        return $user;
    };

    $this->admin = $usuario('catalogo-admin', 'admin-dispensario');
    $this->medico = $usuario('catalogo-medico', 'medico');
    $this->enfermera = $usuario('catalogo-enfermera', 'enfermera');
    $this->otro = $usuario('catalogo-otro', null);
});

test('la_migracion_carga_la_hoja_del_laboratorio', function () {
    expect(CatalogoExamen::count())->toBe(209)
        ->and(CatalogoExamen::distinct()->count('area'))->toBe(18)
        ->and(CatalogoExamen::where('sensible', true)->orderBy('id')->pluck('nombre')->all())->toBe([
            'HIV 1/2/0', 'Anfetaminas', 'Éxtasis', 'Cocaína', 'Opio', 'Tetrahidrocannabinol',
            'HCG beta cualitativo', 'HCG beta cuantitativo', 'Prueba de embarazo',
        ])
        ->and(CatalogoExamen::whereNotNull('etiqueta_nota')->count())->toBe(5)
        ->and(CatalogoExamen::where('nombre', 'Tolerancia oral a la glucosa')->value('etiqueta_nota'))
        ->toBe('Gramos de glucosa');
});

test('el_personal_clinico_lee_el_catalogo_en_el_orden_de_la_hoja', function () {
    $respuesta = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/catalogo-examenes')
        ->assertOk();

    $nombres = collect($respuesta->json('datos'))->pluck('nombre');

    expect($nombres->first())->toBe('Glucosa')
        ->and($nombres->last())->toBe('Electrocardiograma')
        ->and($respuesta->json('meta.areas'))->toHaveCount(18)
        ->and($respuesta->json('meta.areas.0'))->toBe(['value' => 'bioquimica', 'etiqueta' => 'Bioquímica', 'orden' => 1]);
});

test('quien_no_es_del_dispensario_no_lo_lee', function () {
    $this->actingAs($this->otro, 'sanctum')
        ->getJson('/api/v1/dispensario/catalogo-examenes')
        ->assertForbidden();
});

test('la_administracion_agrega_un_examen_al_final_de_su_area', function () {
    $ultimo = CatalogoExamen::where('area', 'hematologia')->max('orden');

    $nuevo = $this->actingAs($this->admin, 'sanctum')
        ->postJson('/api/v1/dispensario/catalogo-examenes', [
            'area' => 'hematologia', 'nombre' => 'Frotis de sangre periférica',
        ])
        ->assertCreated()
        ->json('datos');

    expect($nuevo['orden'])->toBe($ultimo + 1)
        ->and($nuevo['activo'])->toBeTrue()
        ->and($nuevo['sensible'])->toBeFalse();
});

test('no_repite_un_examen_en_la_misma_area_pero_si_en_otra', function () {
    $this->actingAs($this->admin, 'sanctum');

    $errores = $this->postJson('/api/v1/dispensario/catalogo-examenes', [
        'area' => 'electrolitos', 'nombre' => 'Sodio',
    ])->assertStatus(422)->json('errores');

    expect($errores['nombre'][0] ?? null)->toBe('Ese examen ya está en el catálogo, en la misma área.');

    // «Sodio en orina» y «Sodio» en sangre son exámenes distintos.
    $this->postJson('/api/v1/dispensario/catalogo-examenes', [
        'area' => 'heces', 'nombre' => 'Sodio',
    ])->assertCreated();
});

test('un_examen_retirado_no_se_ofrece_y_solo_la_administracion_lo_ve', function () {
    $papanicolaou = CatalogoExamen::where('nombre', 'Papanicolaou')->sole();

    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/dispensario/catalogo-examenes/{$papanicolaou->id}", ['activo' => false])
        ->assertOk();

    $nombres = fn (User $quien, string $url) => collect(
        $this->actingAs($quien, 'sanctum')->getJson($url)->assertOk()->json('datos')
    )->pluck('nombre');

    expect($nombres($this->medico, '/api/v1/dispensario/catalogo-examenes'))->not->toContain('Papanicolaou')
        ->and($nombres($this->medico, '/api/v1/dispensario/catalogo-examenes?incluir_inactivos=1'))->not->toContain('Papanicolaou')
        ->and($nombres($this->admin, '/api/v1/dispensario/catalogo-examenes?incluir_inactivos=1'))->toContain('Papanicolaou');
});

test('cambiar_de_area_lo_lleva_al_final_de_la_nueva', function () {
    $examen = CatalogoExamen::where('nombre', 'Electrocardiograma')->sole();
    $ultimo = CatalogoExamen::where('area', 'marcadores_cardiacos')->max('orden');

    $actualizado = $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/dispensario/catalogo-examenes/{$examen->id}", ['area' => 'marcadores_cardiacos'])
        ->assertOk()
        ->json('datos');

    expect($actualizado['area'])->toBe('marcadores_cardiacos')
        ->and($actualizado['orden'])->toBe($ultimo + 1);
});

test('el_medico_no_cambia_el_catalogo', function () {
    $this->actingAs($this->medico, 'sanctum');

    $this->postJson('/api/v1/dispensario/catalogo-examenes', [
        'area' => 'hematologia', 'nombre' => 'Frotis',
    ])->assertForbidden();

    $examen = CatalogoExamen::first();
    $this->patchJson("/api/v1/dispensario/catalogo-examenes/{$examen->id}", ['activo' => false])
        ->assertForbidden();

    expect($examen->fresh()->activo)->toBeTrue();
});
