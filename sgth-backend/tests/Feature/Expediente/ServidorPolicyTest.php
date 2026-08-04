<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'servidor', 'guard_name' => 'sanctum']);

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-01', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->servidorPropio = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Propio',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $this->servidorAjeno = Servidor::create([
        'cedula' => '2222222222', 'nombre' => 'Titular', 'apellido' => 'Ajeno',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puesto->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    // El vínculo se hace desde users.servidor_id (servidores.user_id no
    // existe desde 2026_05_27_161227_reestructurar_relacion_users_servidores.php).
    $this->usuario = User::factory()->create(['servidor_id' => $this->servidorPropio->id]);
    $this->usuario->assignRole('servidor');
});

test('un servidor vinculado a su propio expediente puede verlo y actualizarlo', function () {
    $this->actingAs($this->usuario, 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->servidorPropio->id}")
        ->assertStatus(200);

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorPropio->id}", [
        'telefono_celular' => '0999999999',
    ])->assertStatus(200);

    expect($this->servidorPropio->fresh()->telefono_celular)->toBe('0999999999');
});

test('un servidor vinculado a otro expediente no puede verlo ni actualizarlo', function () {
    $this->actingAs($this->usuario, 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}")
        ->assertStatus(403);

    $this->putJson("/api/v1/expediente/servidores/{$this->servidorAjeno->id}", [
        'telefono_celular' => '0999999999',
    ])->assertStatus(403);

    expect($this->servidorAjeno->fresh()->telefono_celular)->toBeNull();
});
