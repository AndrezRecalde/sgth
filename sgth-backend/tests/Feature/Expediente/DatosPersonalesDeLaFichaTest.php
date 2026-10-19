<?php

/*
| Lo que la pestaña Personal del expediente necesita de la ficha (2026-10-19).
|
| La provincia y el cantón de nacimiento se guardaban por su id y la ficha no
| los devolvía por nombre, así que la pestaña no podía enseñarlos. Y el
| vencimiento del pasaporte solo lo aceptaba la edición: ningún alta lo
| registraba y ningún formulario lo enviaba.
*/

use App\Models\Expediente\Servidor;
use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->talentoHumano = User::factory()->create();
    $this->talentoHumano->assignRole('admin-uath');

    $this->provincia = Provincia::create(['nombre' => 'Esmeraldas', 'codigo' => '08']);
    $this->canton = Canton::create([
        'nombre' => 'Quinindé', 'provincia_id' => $this->provincia->id, 'codigo' => '0805',
    ]);

    $this->ficha = fn (string $cedula, array $cambios = []): array => [
        'cedula'                  => $cedula,
        'nombre'                  => 'Rosa',
        'apellido'                => 'Cortez',
        'fecha_nacimiento'        => '1990-05-05',
        'genero'                  => 'femenino',
        'estado_civil'            => 'casado',
        'es_extranjero'           => false,
        'provincia_nacimiento_id' => $this->provincia->id,
        'canton_nacimiento_id'    => $this->canton->id,
        ...$cambios,
    ];
});

test('el detalle del expediente trae el lugar de nacimiento por su nombre', function () {
    $servidor = Servidor::create(($this->ficha)('0805000001'));

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$servidor->id}")
        ->assertOk()
        ->assertJsonPath('datos.provincia_nacimiento', 'Esmeraldas')
        ->assertJsonPath('datos.canton_nacimiento', 'Quinindé');
});

test('una ficha sin lugar de nacimiento lo trae vacío', function () {
    $servidor = Servidor::create(($this->ficha)('0805000002', [
        'provincia_nacimiento_id' => null,
        'canton_nacimiento_id'    => null,
    ]));

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$servidor->id}")
        ->assertOk()
        ->assertJsonPath('datos.provincia_nacimiento', null)
        ->assertJsonPath('datos.canton_nacimiento', null);
});

test('el alta registra el vencimiento del pasaporte', function () {
    $vence = now()->addYears(3)->toDateString();

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson('/api/v1/expediente/servidores/basico', ($this->ficha)('0805000003', [
            'pasaporte_numero'      => 'A1234567',
            'pasaporte_vencimiento' => $vence,
        ]))
        ->assertCreated();

    expect(Servidor::where('cedula', '0805000003')->value('pasaporte_vencimiento')?->toDateString())
        ->toBe($vence);
});

test('el alta rechaza un pasaporte ya vencido, como la edición', function () {
    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson('/api/v1/expediente/servidores/basico', ($this->ficha)('0805000004', [
            'pasaporte_numero'      => 'A1234567',
            'pasaporte_vencimiento' => now()->subDay()->toDateString(),
        ]))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['pasaporte_vencimiento']]);
});
