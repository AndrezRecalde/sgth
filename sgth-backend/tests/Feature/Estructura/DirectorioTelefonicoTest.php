<?php

/*
| El directorio telefónico (docs/plan-roles-y-accesos.md, PR 11).
|
| Lo consulta todo el personal desde el Portal; las extensiones las mantiene
| TIC con `gestionar-directorio`. Antes las editaban admin-ti y admin-uath por
| rol, y el técnico que configura la central no podía.
*/

use App\Models\Estructura\ExtensionTelefonica;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Gestión de Prueba del Directorio']);

    $this->activa = ExtensionTelefonica::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'numero_extension'         => '1201',
        'responsable'              => 'Secretaría de prueba',
        'estado'                   => true,
    ]);

    $this->inactiva = ExtensionTelefonica::create([
        'unidad_administrativa_id' => $this->unidad->id,
        'numero_extension'         => '1202',
        'responsable'              => 'Oficina cerrada de prueba',
        'estado'                   => false,
    ]);

    $this->conRol = function (string $rol): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->datos = [
        'unidad_administrativa_id' => $this->unidad->id,
        'numero_extension'         => '1300',
        'responsable'              => 'Recepción de prueba',
        'estado'                   => true,
    ];
});

it('lo consulta cualquier servidor, solo con las extensiones activas', function () {
    $this->actingAs(($this->conRol)('servidor'), 'sanctum')
        ->getJson('/api/v1/estructura/directorio-telefonico')
        ->assertOk()
        ->assertJsonCount(1, 'datos')
        ->assertJsonPath('datos.0.numero_extension', '1201');
});

it('las inactivas solo las ve quien mantiene el directorio, y si las pide', function () {
    $tecnico = ($this->conRol)('tecnico-dtic');

    $this->actingAs($tecnico, 'sanctum')
        ->getJson('/api/v1/estructura/directorio-telefonico?incluir_inactivas=1')
        ->assertOk()
        ->assertJsonCount(2, 'datos')
        ->assertJsonPath('datos.1.estado', false);

    $this->actingAs($tecnico, 'sanctum')
        ->getJson('/api/v1/estructura/directorio-telefonico')
        ->assertJsonCount(1, 'datos');

    $this->actingAs(($this->conRol)('servidor'), 'sanctum')
        ->getJson('/api/v1/estructura/directorio-telefonico?incluir_inactivas=1')
        ->assertJsonCount(1, 'datos');
});

it('lo mantienen el técnico y el jefe de TIC y admin-ti', function (string $rol) {
    $this->actingAs(($this->conRol)($rol), 'sanctum');

    $id = $this->postJson('/api/v1/estructura/extensiones', $this->datos)
        ->assertCreated()
        ->json('datos.id');

    $this->putJson("/api/v1/estructura/extensiones/{$this->inactiva->id}", [
        ...$this->datos, 'numero_extension' => '1202', 'estado' => true,
    ])->assertOk()->assertJsonPath('datos.estado', true);

    $this->deleteJson("/api/v1/estructura/extensiones/{$id}")->assertOk();

    expect(ExtensionTelefonica::find($id))->toBeNull();
})->with(['tecnico-dtic', 'jefe-tic', 'admin-ti']);

it('al resto le responde 403, también a Talento Humano', function (string $rol) {
    $this->actingAs(($this->conRol)($rol), 'sanctum');

    $this->postJson('/api/v1/estructura/extensiones', $this->datos)->assertForbidden();
    $this->putJson("/api/v1/estructura/extensiones/{$this->activa->id}", $this->datos)->assertForbidden();
    $this->deleteJson("/api/v1/estructura/extensiones/{$this->activa->id}")->assertForbidden();

    expect($this->activa->fresh()->numero_extension)->toBe('1201');
})->with(['admin-uath', 'asistente-uath', 'servidor', 'director', 'auditor', 'comunicacion-social']);
