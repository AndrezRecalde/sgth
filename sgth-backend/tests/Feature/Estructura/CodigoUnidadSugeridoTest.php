<?php

use App\Enums\Permiso;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'gestor-codigos', 'guard_name' => 'sanctum']);
    foreach ([Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_ORGANIGRAMA] as $permiso) {
        $rol->givePermissionTo(
            Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum'])
        );
    }

    $this->gestor = User::factory()->create(['primer_login' => false]);
    $this->gestor->assignRole($rol);

    $this->raiz = UnidadAdministrativa::create([
        'codigo'          => 'GADPE',
        'nombre'          => 'GADPE',
        'unidad_padre_id' => null,
        'nivel'           => 1,
        'estado'          => true,
    ]);
});

function sugerir(int $padreId)
{
    return test()->actingAs(test()->gestor, 'sanctum')->getJson(
        '/api/v1/estructura/unidades-administrativas/sugerir-codigo?unidad_padre_id='.$padreId
    );
}

test('el primer hijo de la institución recibe el sufijo 01', function () {
    sugerir($this->raiz->id)
        ->assertStatus(200)
        ->assertJsonPath('datos.codigo', 'GADPE-01');
});

test('el código sigue la jerarquía hasta el tercer nivel', function () {
    $gestion = UnidadAdministrativa::create([
        'codigo'          => 'GADPE-01',
        'nombre'          => 'Gestión Administrativa',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
        'estado'          => true,
    ]);

    UnidadAdministrativa::create([
        'codigo'          => 'GADPE-01-01',
        'nombre'          => 'Activos Fijos',
        'unidad_padre_id' => $gestion->id,
        'nivel'           => 3,
        'estado'          => true,
    ]);

    // El código dice dónde está la unidad en el árbol: ese es todo el punto
    // de derivarlo de la jerarquía en vez de sortear unas letras.
    sugerir($gestion->id)
        ->assertStatus(200)
        ->assertJsonPath('datos.codigo', 'GADPE-01-02');
});

test('el secuencial salta los códigos ocupados por unidades inactivas o borradas', function () {
    UnidadAdministrativa::create([
        'codigo'          => 'GADPE-01',
        'nombre'          => 'Gestión inactiva',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
        'estado'          => false,
    ]);

    $borrada = UnidadAdministrativa::create([
        'codigo'          => 'GADPE-02',
        'nombre'          => 'Gestión borrada',
        'unidad_padre_id' => $this->raiz->id,
        'nivel'           => 2,
        'estado'          => true,
    ]);
    $borrada->delete();

    // `codigo` es único incluso para las borradas por software: reutilizar su
    // número devolvería una sugerencia que la base rechaza al guardar.
    sugerir($this->raiz->id)
        ->assertStatus(200)
        ->assertJsonPath('datos.codigo', 'GADPE-03');
});

test('sin unidad superior no hay código que sugerir', function () {
    $this->actingAs($this->gestor, 'sanctum')
        ->getJson('/api/v1/estructura/unidades-administrativas/sugerir-codigo')
        ->assertStatus(200)
        ->assertJsonPath('datos.codigo', '');
});

test('sin permiso de gestionar organigrama no se sugiere código', function () {
    $sinPermiso = User::factory()->create(['primer_login' => false]);

    $this->actingAs($sinPermiso, 'sanctum')
        ->getJson(
            '/api/v1/estructura/unidades-administrativas/sugerir-codigo?unidad_padre_id='.$this->raiz->id
        )
        ->assertStatus(403);
});
