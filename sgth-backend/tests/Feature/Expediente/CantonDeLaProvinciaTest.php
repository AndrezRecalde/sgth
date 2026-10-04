<?php

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * El cantón de nacimiento tiene que ser de la provincia de nacimiento.
 *
 * Hasta el 2026-10-03 el backend aceptaba cualquier cantón del catálogo, y el
 * formulario no enviaba el cantón que vaciaba al cambiar de provincia: la
 * ficha quedaba con la provincia nueva y el cantón de otra.
 */

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $provincia = fn (string $nombre, string $codigo) => DB::table('provincias')->insertGetId(
        ['nombre' => $nombre, 'codigo' => $codigo, 'created_at' => now(), 'updated_at' => now()]
    );
    $canton = fn (string $nombre, int $provinciaId) => DB::table('cantones')->insertGetId(
        ['nombre' => $nombre, 'provincia_id' => $provinciaId, 'created_at' => now(), 'updated_at' => now()]
    );
    $this->esmeraldas = $provincia('Esmeraldas', '08');
    $this->pichincha  = $provincia('Pichincha', '17');
    $this->quininde   = $canton('Quinindé', $this->esmeraldas);
    $this->quito      = $canton('Quito', $this->pichincha);

    $unidad = unidadDePrueba(['nombre' => 'Unidad Cantones']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0801010101', 'nombre' => 'Ana', 'apellido' => 'Cantón',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Cantones')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true, 'es_extranjero' => false,
        'provincia_nacimiento_id' => $this->esmeraldas, 'canton_nacimiento_id' => $this->quininde,
    ]);
    $this->url = "/api/v1/expediente/servidores/{$this->servidor->id}";
});

test('no se guarda un cantón de otra provincia', function () {
    $this->putJson($this->url, ['canton_nacimiento_id' => $this->quito])
        ->assertUnprocessable()
        ->assertJsonPath('errores.canton_nacimiento_id.0', 'El cantón de nacimiento no pertenece a la provincia elegida.');
});

test('cambiar solo la provincia no deja el cantón viejo apuntando a otra', function () {
    $this->putJson($this->url, ['provincia_nacimiento_id' => $this->pichincha])
        ->assertUnprocessable()
        ->assertJsonPath('errores.canton_nacimiento_id.0', 'El cantón de nacimiento no pertenece a la provincia elegida.');

    // Con el cantón vaciado —lo que el formulario envía ahora— o el nuevo, sí.
    $this->putJson($this->url, ['provincia_nacimiento_id' => $this->pichincha, 'canton_nacimiento_id' => null])
        ->assertOk();
    $this->putJson($this->url, ['canton_nacimiento_id' => $this->quito])->assertOk();

    expect($this->servidor->fresh())
        ->provincia_nacimiento_id->toBe($this->pichincha)
        ->canton_nacimiento_id->toBe($this->quito);
});
