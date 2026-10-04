<?php

use App\Models\Catalogo\EntidadFinanciera;
use App\Models\Expediente\CuentaBancariaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Cuál es la cuenta principal de nómina y la de viáticos.
 *
 * Hasta el 2026-10-03 podía no haber ninguna —la primera cuenta no se marcaba
 * sola, y borrar o desmarcar la principal dejaba el hueco—, y por API una
 * cuenta «solo viáticos» podía quedar como la principal de nómina.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Cuentas']);
    $servidor = Servidor::forceCreate([
        'cedula' => '0802020201', 'nombre' => 'Luz', 'apellido' => 'Cuenta',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Cuentas')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->url = "/api/v1/expediente/servidores/{$servidor->id}/cuentas-bancarias";
    $this->servidorId = $servidor->id;

    $banco = EntidadFinanciera::query()->first()
        ?? EntidadFinanciera::forceCreate(['nombre' => 'Banco de Prueba', 'tipo' => 'banco', 'codigo_bce' => '999', 'estado' => true]);
    $this->cuenta = fn (string $proposito, array $extra = []) => [
        'entidad_financiera_id' => $banco->id, 'tipo_cuenta' => 'ahorros',
        'numero_cuenta' => (string) random_int(1000000, 9999999), 'proposito' => $proposito, ...$extra,
    ];
    $this->principales = fn () => CuentaBancariaServidor::where('servidor_id', $this->servidorId)
        ->orderBy('id')->get(['id', 'es_principal_sueldo', 'es_principal_viatico'])
        ->map(fn ($c) => [$c->es_principal_sueldo, $c->es_principal_viatico])->all();
});

test('la primera cuenta que paga algo queda como su principal', function () {
    $this->postJson($this->url, ($this->cuenta)('ambos'))->assertCreated();

    expect(($this->principales)())->toBe([[true, true]]);
});

test('borrar la principal pasa la marca a otra cuenta compatible', function () {
    $primera = $this->postJson($this->url, ($this->cuenta)('sueldo'))->json('datos.id');
    $this->postJson($this->url, ($this->cuenta)('ambos'))->assertCreated();

    // La primera es la de nómina; la segunda, la única de viáticos.
    expect(($this->principales)())->toBe([[true, false], [false, true]]);

    $this->deleteJson("{$this->url}/{$primera}")->assertOk();

    expect(($this->principales)())->toBe([[true, true]]);
});

test('desmarcar la única cuenta compatible no deja el propósito sin principal', function () {
    $id = $this->postJson($this->url, ($this->cuenta)('sueldo'))->json('datos.id');

    $this->putJson("{$this->url}/{$id}", ($this->cuenta)('sueldo', ['es_principal_sueldo' => false]))
        ->assertOk();

    expect(($this->principales)())->toBe([[true, false]]);
});

test('una cuenta solo de viáticos no es la principal de nómina, tampoco por API', function () {
    $this->postJson($this->url, ($this->cuenta)('viaticos', ['es_principal_sueldo' => true]))
        ->assertUnprocessable()
        ->assertJsonPath('mensaje', 'Una cuenta solo de viáticos no puede ser la principal de nómina.');

    $id = $this->postJson($this->url, ($this->cuenta)('viaticos'))->json('datos.id');
    $this->postJson("{$this->url}/{$id}/set-principal", ['proposito' => 'sueldo'])
        ->assertUnprocessable();
});
