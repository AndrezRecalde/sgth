<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * admin-ti no entra al Expediente Digital (decisión de Talento Humano,
 * 2026-10-03).
 *
 * El `Gate::before` le daba todo, así que la ficha de cualquiera le abría por
 * ServidorPolicy mientras la mitad de las pestañas —con rol en la ruta— le
 * respondían 403. Ahora, cuando lo que se autoriza es un Servidor, decide la
 * policy, que no nombra a admin-ti.
 */

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Unidad Admin TI']);
    $crear = fn (string $cedula, string $nombre) => Servidor::forceCreate([
        'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Soporte',
        'puesto_id' => puestoDePrueba($unidad, "Puesto {$nombre}")->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->ajeno = $crear('0807070701', 'Ajeno');
    $this->propio = $crear('0807070702', 'Propio');

    $this->ti = User::factory()->create(['servidor_id' => $this->propio->id]);
    $this->ti->assignRole('admin-ti');
});

test('admin-ti no lista, no abre ni exporta expedientes ajenos', function () {
    $this->actingAs($this->ti, 'sanctum');

    $this->getJson('/api/v1/expediente/servidores')->assertForbidden();
    $this->getJson("/api/v1/expediente/servidores/{$this->ajeno->id}")->assertForbidden();
    $this->getJson("/api/v1/expediente/servidores/{$this->ajeno->id}/documentos")->assertForbidden();
    $this->getJson("/api/v1/expediente/servidores/{$this->ajeno->id}/movimientos")->assertForbidden();
    $this->get('/api/v1/expediente/servidores-export/excel', ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('admin-ti ve su propia ficha, como cualquier servidor', function () {
    $this->actingAs($this->ti, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$this->propio->id}")
        ->assertOk();
});

test('quien tiene admin-ti y además admin-uath sigue entrando por admin-uath', function () {
    $this->ti->assignRole('admin-uath');

    $this->actingAs($this->ti, 'sanctum');
    $this->getJson('/api/v1/expediente/servidores')->assertOk();
    $this->getJson("/api/v1/expediente/servidores/{$this->ajeno->id}")->assertOk();
});

test('admin-ti conserva el buscador de servidores sin cuenta del módulo de Usuarios', function () {
    $this->actingAs($this->ti, 'sanctum')
        ->getJson('/api/v1/expediente/servidores/sin-usuario')
        ->assertOk();

    // Es de quien gestiona usuarios: Talento Humano no tiene ese módulo.
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum')
        ->getJson('/api/v1/expediente/servidores/sin-usuario')
        ->assertForbidden();
});

test('fuera del Expediente el atajo de admin-ti sigue igual', function () {
    // Un permiso suelto, que no es un Servidor: lo sigue concediendo el atajo.
    expect($this->ti->can('ver-historia-clinica'))->toBeTrue();
});
