<?php

/*
| La agenda con dueño (docs/plan-roles-y-accesos.md, PR 6).
|
| Cualquiera de los cuatro roles del Dispensario listaba la cola entera y
| podía cancelar, marcar como no presentado, reactivar o pasar a consulta el
| turno de otro profesional. Ahora Enfermería y la administración llevan la
| cola de todos; el médico y el odontólogo, la suya. Y la cola de cada uno se
| pide por especialidad: Consultas y Odontología enseñaban la misma lista.
*/

use App\Models\Dispensario\AgendaMedica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolPermisoSeeder::class);

    $this->conRol = function (string ...$roles): User {
        $usuario = User::factory()->create();
        $usuario->assignRole($roles);

        return $usuario;
    };

    $this->medico = ($this->conRol)('medico');
    $this->colega = ($this->conRol)('medico');

    $paciente = Servidor::forceCreate(['cedula' => '0810101001', 'nombre' => 'Lucía', 'apellido' => 'Cortez']);

    $this->turnoDe = fn (User $quien, string $tipo = 'medicina_general') => AgendaMedica::forceCreate([
        'medico_id' => $quien->id,
        'servidor_id' => $paciente->id,
        'fecha' => now()->toDateString(),
        'tipo_atencion' => $tipo,
        'estado' => 'en_espera',
        'registrado_en' => now(),
    ]);
});

it('el médico lista solo su cola, aunque pida la de otro', function () {
    $suyo = ($this->turnoDe)($this->medico);
    $ajeno = ($this->turnoDe)($this->colega);

    $this->actingAs($this->medico, 'sanctum');

    $ids = fn (string $query = '') => $this->getJson("/api/v1/dispensario/agenda{$query}")
        ->assertOk()->collect('datos.data')->pluck('id')->all();

    expect($ids())->toBe([$suyo->id])
        ->and($ids("?medico_id={$this->colega->id}"))->toBe([$suyo->id])
        ->and($ids())->not->toContain($ajeno->id);
});

it('Enfermería y la administración ven la cola de todos', function (string $rol) {
    ($this->turnoDe)($this->medico);
    ($this->turnoDe)($this->colega);

    $this->actingAs(($this->conRol)($rol), 'sanctum')
        ->getJson('/api/v1/dispensario/agenda')
        ->assertOk()
        ->assertJsonCount(2, 'datos.data');
})->with(['enfermera', 'admin-dispensario']);

it('el médico no abre, no cancela ni marca el turno de otro', function () {
    $ajeno = ($this->turnoDe)($this->colega);

    $this->actingAs($this->medico, 'sanctum');

    $this->getJson("/api/v1/dispensario/agenda/{$ajeno->id}")->assertForbidden();
    $this->deleteJson("/api/v1/dispensario/agenda/{$ajeno->id}")->assertForbidden();
    $this->patchJson("/api/v1/dispensario/agenda/{$ajeno->id}/no-presentado")->assertForbidden();
    $this->patchJson("/api/v1/dispensario/agenda/{$ajeno->id}/en-consulta")->assertForbidden();
    $this->patchJson("/api/v1/dispensario/agenda/{$ajeno->id}/reactivar")->assertForbidden();

    expect($ajeno->fresh()->estado)->toBe('en_espera');
});

it('el médico sí opera su turno', function () {
    $suyo = ($this->turnoDe)($this->medico);

    $this->actingAs($this->medico, 'sanctum');

    $this->getJson("/api/v1/dispensario/agenda/{$suyo->id}")->assertOk();
    $this->patchJson("/api/v1/dispensario/agenda/{$suyo->id}/no-presentado")->assertOk();

    expect($suyo->fresh()->estado)->toBe('no_presentado');
});

it('Enfermería cancela el turno de cualquiera', function () {
    $turno = ($this->turnoDe)($this->medico);

    $this->actingAs(($this->conRol)('enfermera'), 'sanctum')
        ->deleteJson("/api/v1/dispensario/agenda/{$turno->id}")
        ->assertOk();

    expect($turno->fresh()->estado)->toBe('cancelada');
});

it('el turno que no existe sigue dando 404', function () {
    $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/agenda/999999')
        ->assertNotFound();
});

it('Consultas y Odontología piden cada una su cola', function () {
    // Quien es médico y odontólogo veía sus turnos de odontología en Consultas.
    $doble = ($this->conRol)('medico', 'odontologo');
    $deMedicina = ($this->turnoDe)($doble);
    $deOdontologia = ($this->turnoDe)($doble, 'odontologia');

    $this->actingAs($doble, 'sanctum');

    $cola = fn (string $tipo) => $this->getJson("/api/v1/dispensario/agenda/turnos-del-dia?tipo_atencion={$tipo}")
        ->assertOk()->collect('datos')->pluck('id')->all();

    expect($cola('medicina_general'))->toBe([$deMedicina->id])
        ->and($cola('odontologia'))->toBe([$deOdontologia->id]);

    $this->getJson('/api/v1/dispensario/agenda/turnos-del-dia?tipo_atencion=inventada')
        ->assertUnprocessable();
});
