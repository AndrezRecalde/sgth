<?php

use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\PerfilExamen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Los perfiles de exámenes (fase 1 de docs/diseno-examenes-medicos.md): grupos
 * que el médico pide con un clic. Los arman el médico, el odontólogo (los de su
 * especialidad) y la administración; uno ocupacional no lleva sensibles.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $usuario = function (string $alias, string $rol) {
        $user = User::create([
            'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
            'password' => bcrypt('123456'), 'primer_login' => false,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

        return $user;
    };

    $this->medico = $usuario('perfiles-medico', 'medico');
    $this->odontologo = $usuario('perfiles-odontologo', 'odontologo');
    $this->enfermera = $usuario('perfiles-enfermera', 'enfermera');
    $this->admin = $usuario('perfiles-admin', 'admin-dispensario');
});

/** Los ids de los exámenes del catálogo, por nombre. */
function idsDelCatalogo(string ...$nombres): array
{
    return array_map(
        fn (string $nombre) => CatalogoExamen::where('nombre', $nombre)->value('id'),
        $nombres,
    );
}

function perfilDeExamenes(array $datos): array
{
    return [
        'especialidad' => 'medicina_general',
        'nombre'       => 'Control metabólico',
        'examenes'     => idsDelCatalogo('Glucosa', 'Glicohemoglobina (HbA1c)', 'Triglicéridos'),
        ...$datos,
    ];
}

test('el_medico_arma_un_perfil_de_su_especialidad', function () {
    $perfil = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([]))
        ->assertCreated()
        ->json('datos');

    expect(collect($perfil['examenes'])->pluck('nombre')->sort()->values()->all())
        ->toBe(['Glicohemoglobina (HbA1c)', 'Glucosa', 'Triglicéridos'])
        ->and($perfil['activo'])->toBeTrue()
        ->and($perfil['ocupacional'])->toBeFalse()
        ->and($perfil['created_by'])->toBe($this->medico->id);
});

test('el_odontologo_arma_los_suyos_y_no_los_de_medicina', function () {
    $this->actingAs($this->odontologo, 'sanctum');

    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([]))
        ->assertForbidden();

    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
        'especialidad' => 'odontologia', 'nombre' => 'Antes de una extracción',
        'examenes' => idsDelCatalogo('Rx periapical', 'Tiempo de coagulación', 'Tiempo de sangría'),
    ]))->assertCreated();
});

test('un_perfil_ocupacional_no_lleva_examenes_sensibles', function () {
    $this->actingAs($this->medico, 'sanctum');

    $errores = $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
        'nombre' => 'Ingreso', 'ocupacional' => true,
        'examenes' => idsDelCatalogo('Hemograma completo', 'HIV 1/2/0', 'Cocaína'),
    ]))->assertStatus(422)->json('errores');

    expect($errores['examenes'][0] ?? null)
        ->toBe('Un perfil ocupacional no puede llevar exámenes sensibles: Cocaína, HIV 1/2/0.');

    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
        'nombre' => 'Ingreso', 'ocupacional' => true,
        'examenes' => idsDelCatalogo('Hemograma completo', 'Audiometría tonal'),
    ]))->assertCreated();
});

test('no_lleva_examenes_retirados_del_catalogo', function () {
    CatalogoExamen::where('nombre', 'Fructosamina')->update(['activo' => false]);

    $errores = $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
            'examenes' => idsDelCatalogo('Fructosamina'),
        ]))->assertStatus(422)->json('errores');

    expect($errores['examenes.0'][0] ?? null)->toBe('Ese examen no está activo en el catálogo.');
});

test('el_nombre_no_se_repite_dentro_de_una_especialidad', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([]))->assertCreated();
    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([]))->assertStatus(422);
    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
        'especialidad' => 'odontologia', 'examenes' => idsDelCatalogo('Glucosa'),
    ]))->assertCreated();
});

test('editar_reemplaza_los_examenes_y_vigila_los_sensibles_que_ya_tenia', function () {
    $this->actingAs($this->medico, 'sanctum');

    $perfil = $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([
        'nombre' => 'Control', 'examenes' => idsDelCatalogo('Glucosa', 'Prueba de embarazo'),
    ]))->assertCreated()->json('datos');

    // Pasarlo a ocupacional con un sensible dentro no vale, aunque no se
    // manden los exámenes: cuentan los que ya tiene.
    $this->patchJson("/api/v1/dispensario/perfiles-examen/{$perfil['id']}", ['ocupacional' => true])
        ->assertStatus(422);

    $editado = $this->patchJson("/api/v1/dispensario/perfiles-examen/{$perfil['id']}", [
        'ocupacional' => true, 'examenes' => idsDelCatalogo('Glucosa', 'Espirometría'),
    ])->assertOk()->json('datos');

    expect(collect($editado['examenes'])->pluck('nombre')->sort()->values()->all())
        ->toBe(['Espirometría', 'Glucosa'])
        ->and($editado['ocupacional'])->toBeTrue();
});

test('un_perfil_retirado_no_se_ofrece', function () {
    $perfil = PerfilExamen::create([
        'nombre' => 'Viejo', 'especialidad' => 'medicina_general', 'created_by' => $this->medico->id,
    ]);
    $perfil->examenes()->sync(idsDelCatalogo('Glucosa'));

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/perfiles-examen/{$perfil->id}", ['activo' => false])
        ->assertOk();

    $nombres = fn (string $url) => collect(
        $this->actingAs($this->admin, 'sanctum')->getJson($url)->assertOk()->json('datos')
    )->pluck('nombre')->all();

    expect($nombres('/api/v1/dispensario/perfiles-examen'))->toBe([])
        ->and($nombres('/api/v1/dispensario/perfiles-examen?incluir_inactivos=1'))->toBe(['Viejo']);
});

test('se_filtran_por_especialidad', function () {
    foreach (['medicina_general' => 'Uno', 'odontologia' => 'Dos'] as $especialidad => $nombre) {
        PerfilExamen::create(['nombre' => $nombre, 'especialidad' => $especialidad])
            ->examenes()->sync(idsDelCatalogo('Glucosa'));
    }

    $datos = $this->actingAs($this->odontologo, 'sanctum')
        ->getJson('/api/v1/dispensario/perfiles-examen?especialidad=odontologia')
        ->assertOk()->json('datos');

    expect(collect($datos)->pluck('nombre')->all())->toBe(['Dos']);
});

test('enfermeria_ni_los_lee_ni_los_arma', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    // Leerlos, solo hasta el 2026-10-24.
    $this->getJson('/api/v1/dispensario/perfiles-examen')->assertForbidden();
    $this->postJson('/api/v1/dispensario/perfiles-examen', perfilDeExamenes([]))->assertForbidden();
});
