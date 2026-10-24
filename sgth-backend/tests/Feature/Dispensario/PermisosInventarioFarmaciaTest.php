<?php

/*
| Quién cambia el inventario de la farmacia (decisión del Dispensario del
| 2026-10-24, pregunta 2). Todos lo consultan; dar de alta, editar, retirar
| un medicamento y ajustar el stock, la administración; dar de baja
| existencias, la administración y enfermería. Antes los cuatro roles podían
| todo, ajustar el stock sin documento incluido.
|
| Un cuerpo vacío basta: la regla de rol responde 403 antes que la
| validación, así que un 422 dice que el rol pasó.
*/

use App\Models\Dispensario\InventarioMedicina;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $this->medicina = InventarioMedicina::create([
        'codigo' => 'MED-PERM-1', 'nombre' => 'Ibuprofeno', 'principio_activo' => 'ibuprofeno',
        'presentacion' => 'tableta', 'stock_actual' => 0, 'stock_minimo' => 0, 'estado' => true,
    ]);
});

function usuarioInventarioFarmacia(string $rol): User
{
    $user = User::create([
        'email' => "inventario-{$rol}@example.com", 'usuario_ti' => "inventario-{$rol}",
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

/** Lo que responde cada acción del inventario a ese rol, con un cuerpo vacío. */
function accionesInventarioFarmacia(User $quien, InventarioMedicina $medicina): array
{
    test()->actingAs($quien, 'sanctum');
    $base = '/api/v1/dispensario/inventario/medicinas';

    return [
        'consultar'  => test()->getJson($base)->status(),
        'kardex'     => test()->getJson("{$base}/{$medicina->id}/kardex")->status(),
        'alta'       => test()->postJson($base, [])->status(),
        'editar'     => test()->putJson("{$base}/{$medicina->id}", ['nombre' => ''])->status(),
        'ajustar'    => test()->postJson("{$base}/{$medicina->id}/ajustar-inventario", [])->status(),
        'baja'       => test()->postJson("{$base}/{$medicina->id}/baja", [])->status(),
    ];
}

test('médicos y odontólogos solo consultan', function (string $rol) {
    expect(accionesInventarioFarmacia(usuarioInventarioFarmacia($rol), $this->medicina))->toBe([
        'consultar' => 200, 'kardex' => 200,
        'alta' => 403, 'editar' => 403, 'ajustar' => 403, 'baja' => 403,
    ]);

    $this->deleteJson("/api/v1/dispensario/inventario/medicinas/{$this->medicina->id}")->assertForbidden();
    expect($this->medicina->fresh()->estado)->toBeTrue();
})->with(['medico', 'odontologo']);

test('enfermería consulta y da de baja existencias, nada más', function () {
    expect(accionesInventarioFarmacia(usuarioInventarioFarmacia('enfermera'), $this->medicina))->toBe([
        'consultar' => 200, 'kardex' => 200,
        'alta' => 403, 'editar' => 403, 'ajustar' => 403, 'baja' => 422,
    ]);

    $this->deleteJson("/api/v1/dispensario/inventario/medicinas/{$this->medicina->id}")->assertForbidden();
});

test('la administración puede todo', function () {
    expect(accionesInventarioFarmacia(usuarioInventarioFarmacia('admin-dispensario'), $this->medicina))->toBe([
        'consultar' => 200, 'kardex' => 200,
        'alta' => 422, 'editar' => 422, 'ajustar' => 422, 'baja' => 422,
    ]);

    $this->deleteJson("/api/v1/dispensario/inventario/medicinas/{$this->medicina->id}")->assertOk();
    expect($this->medicina->fresh()->estado)->toBeFalse();
});
