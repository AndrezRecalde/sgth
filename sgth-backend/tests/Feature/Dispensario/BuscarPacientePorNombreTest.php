<?php

use App\Enums\RegimenLaboral;
use App\Enums\TipoParentesco;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->enfermera = User::forceCreate([
        'email' => 'enfbusca@example.com', 'usuario_ti' => 'enfbusca',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->enfermera->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $unidad = unidadDePrueba(['nombre' => 'Dirección Búsqueda']);
    $this->titular = Servidor::forceCreate([
        'cedula' => '0803333333', 'nombre' => 'Nelson', 'apellido' => 'Arroyo Quiñónez',
        'puesto_id' => puestoDePrueba($unidad, 'Analista Búsqueda')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2),
        'estado' => true,
    ]);

    $this->hija = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0803333334',
        'nombres' => 'Camila Sofía', 'apellidos' => 'Arroyo Vera',
        'parentesco' => TipoParentesco::HIJO,
        'fecha_nacimiento' => now()->subYears(12), 'estado' => true,
    ]);
});

function buscarPacientesPorNombre(string $q): array
{
    return test()->getJson('/api/v1/dispensario/pacientes/buscar-por-nombre?q=' . urlencode($q))
        ->assertOk()->json('datos');
}

test('encuentra servidor y familiar por apellido, sin tildes y en cualquier orden', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $porApellido = buscarPacientesPorNombre('arroyo');
    expect(collect($porApellido)->pluck('tipo')->all())->toBe(['servidor', 'beneficiario']);

    // «quinonez» sin eñe ni tilde, y «sofia» sin tilde.
    expect(collect(buscarPacientesPorNombre('quinonez'))->pluck('id')->all())->toBe([$this->titular->id]);

    $hija = buscarPacientesPorNombre('vera SOFIA camila');
    expect($hija)->toHaveCount(1);
    expect($hija[0])->toMatchArray([
        'tipo' => 'beneficiario', 'id' => $this->hija->id, 'cedula' => '0803333334',
        'servidor_titular' => 'Nelson Arroyo Quiñónez', 'tiene_historia_clinica' => false,
    ]);
});

test('un familiar dado de baja no aparece', function () {
    $this->actingAs($this->enfermera, 'sanctum');
    $this->hija->update(['estado' => false]);

    expect(collect(buscarPacientesPorNombre('arroyo'))->pluck('tipo')->all())->toBe(['servidor']);
});

test('pide al menos tres letras y trata % como texto', function () {
    $this->actingAs($this->enfermera, 'sanctum');

    $this->getJson('/api/v1/dispensario/pacientes/buscar-por-nombre?q=ar')->assertUnprocessable();
    expect(buscarPacientesPorNombre('%%%'))->toBe([]);
});

test('sin rol del dispensario no se busca', function () {
    $this->actingAs(User::forceCreate([
        'email' => 'nadie@example.com', 'usuario_ti' => 'nadie',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]), 'sanctum');

    $this->getJson('/api/v1/dispensario/pacientes/buscar-por-nombre?q=arroyo')->assertForbidden();
});
