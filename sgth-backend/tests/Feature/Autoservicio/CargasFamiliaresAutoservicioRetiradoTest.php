<?php

/*
| `autoservicio/mis-cargas-familiares` (GET, POST, PUT y DELETE) se retiró el
| 2026-10-03.
|
| Ninguna pantalla lo usaba, y no pedía rol: cualquier servidor podía, por API,
| darse de alta familiares —que entran como pacientes del Dispensario—,
| marcarles discapacidad o enfermedad catastrófica, o borrar los que había
| registrado Talento Humano. Las cargas familiares las registra Talento Humano
| desde el Expediente; el titular las ve en «Mi expediente».
*/

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();
    $this->titular = Servidor::forceCreate([
        'cedula' => '0805555551', 'nombre' => 'Luis', 'apellido' => 'Portal',
        'puesto_id' => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $this->usuario = User::factory()->create(['servidor_id' => $this->titular->id]);
    $this->usuario->assignRole('servidor');

    $this->registradaPorTh = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0805555552',
        'nombres' => 'Eva', 'apellidos' => 'Portal', 'parentesco' => 'hijo',
        'fecha_nacimiento' => '2014-01-01', 'genero' => 'femenino',
        'persona_con_discapacidad' => false, 'posee_enfermedad_catastrofica' => false,
    ]);
});

test('el titular ya no se da de alta familiares por API', function () {
    $this->actingAs($this->usuario, 'sanctum')
        ->postJson('/api/v1/autoservicio/mis-cargas-familiares', [
            'cedula' => '0805555553', 'nombres' => 'Teo', 'apellidos' => 'Portal',
            'parentesco' => 'hijo', 'fecha_nacimiento' => '2018-01-01', 'genero' => 'masculino',
            'persona_con_discapacidad' => true, 'posee_enfermedad_catastrofica' => false,
        ])
        ->assertNotFound();

    expect(CargaFamiliar::where('cedula', '0805555553')->exists())->toBeFalse();
});

test('el titular ya no edita ni borra los familiares que registró Talento Humano', function () {
    $url = "/api/v1/autoservicio/mis-cargas-familiares/{$this->registradaPorTh->id}";
    $this->actingAs($this->usuario, 'sanctum');

    $this->putJson($url, ['nombres' => 'Otra', 'posee_enfermedad_catastrofica' => true])
        ->assertNotFound();
    $this->deleteJson($url)->assertNotFound();
    $this->getJson('/api/v1/autoservicio/mis-cargas-familiares')->assertNotFound();

    expect($this->registradaPorTh->fresh())
        ->not->toBeNull()
        ->nombres->toBe('Eva')
        ->posee_enfermedad_catastrofica->toBeFalse();
});
