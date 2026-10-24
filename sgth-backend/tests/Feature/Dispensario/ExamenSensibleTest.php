<?php

/*
| Un examen que pasa a ser sensible sale de todo lo ocupacional (D8; decisión
| del Dispensario del 2026-10-24): al marcarlo desde el catálogo se quita de
| los perfiles ocupacionales y de la lista por riesgo, y la respuesta dice de
| cuáles. VDRL y hepatitis B y C ya vienen sensibles.
*/

use App\Models\Dispensario\CatalogoExamen;
use App\Models\Dispensario\PerfilExamen;
use App\Models\Dispensario\RiesgoExamen;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->admin = User::create([
        'email' => 'admin-sensible@example.com', 'usuario_ti' => 'admin-sensible',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']));

    $this->examen = CatalogoExamen::where('nombre', 'Hemograma completo')->firstOrFail();
    $otro = CatalogoExamen::where('nombre', 'Glucosa')->firstOrFail();

    $perfil = fn (string $nombre, bool $ocupacional) => tap(
        PerfilExamen::create(['nombre' => $nombre, 'especialidad' => 'medicina_general', 'ocupacional' => $ocupacional]),
        fn (PerfilExamen $p) => $p->examenes()->attach([$this->examen->id, $otro->id]),
    );
    $this->ingreso = $perfil('Ingreso administrativo', true);
    $this->periodico = $perfil('Periodico de campo', true);
    $this->control = $perfil('Control metabolico', false);

    RiesgoExamen::create(['categoria' => 'fisico', 'factor' => 'Ruido', 'catalogo_examen_id' => $this->examen->id]);
    RiesgoExamen::create(['categoria' => 'fisico', 'factor' => 'Ruido', 'catalogo_examen_id' => $otro->id]);

    $this->actingAs($this->admin, 'sanctum');
});

test('al marcarlo sensible sale de los perfiles ocupacionales y de la lista por riesgo, y lo dice', function () {
    $this->patchJson("/api/v1/dispensario/catalogo-examenes/{$this->examen->id}", ['sensible' => true])
        ->assertOk()
        ->assertJsonPath('datos.sensible', true)
        ->assertJsonPath('meta.quitado_de.perfiles', ['Ingreso administrativo', 'Periodico de campo'])
        ->assertJsonPath('meta.quitado_de.riesgos', ['Físico · Ruido'])
        ->assertJsonPath('mensaje', 'Examen «Hemograma completo» actualizado. Como ahora es sensible, se quitó de los perfiles ocupacionales «Ingreso administrativo», «Periodico de campo» y de la lista por riesgo (Físico · Ruido).');

    $examenesDe = fn (PerfilExamen $p) => $p->examenes()->pluck('nombre')->all();

    expect($examenesDe($this->ingreso))->toBe(['Glucosa'])
        ->and($examenesDe($this->periodico))->toBe(['Glucosa'])
        // Un perfil que no es ocupacional lo conserva: ahí pide el consentimiento.
        ->and($examenesDe($this->control))->toContain('Hemograma completo')
        ->and(RiesgoExamen::where('catalogo_examen_id', $this->examen->id)->exists())->toBeFalse()
        ->and(RiesgoExamen::count())->toBe(1);
});

test('editar otra cosa, o un examen que ya era sensible, no quita nada', function () {
    $this->patchJson("/api/v1/dispensario/catalogo-examenes/{$this->examen->id}", ['indicaciones' => 'Ayuno de 8 horas'])
        ->assertOk()
        ->assertJsonPath('mensaje', 'Examen «Hemograma completo» actualizado.')
        ->assertJsonPath('meta.quitado_de.perfiles', []);

    expect($this->ingreso->examenes()->count())->toBe(2)
        ->and(RiesgoExamen::count())->toBe(2);
});

test('VDRL y hepatitis B y C vienen sensibles, y la hepatitis A no', function () {
    $sensible = fn (string $nombre) => CatalogoExamen::where('nombre', $nombre)->value('sensible');

    foreach (['VDRL', 'HBsAg cualitativo', 'HBsAg IgG', 'HCV cualitativo', 'HCV IgG', 'HCV IgM'] as $nombre) {
        expect($sensible($nombre))->toBeTrue("{$nombre} debería ser sensible");
    }
    expect($sensible('HAV IgG'))->toBeFalse();
});
