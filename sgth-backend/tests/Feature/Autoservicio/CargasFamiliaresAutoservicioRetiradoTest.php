<?php

/*
| `autoservicio/mis-cargas-familiares` (GET, POST, PUT y DELETE) se retiró el
| 2026-10-03.
|
| Ninguna pantalla lo usaba, y no pedía rol: cualquier servidor podía, por API,
| darse de alta familiares —que entran como pacientes del Dispensario—,
| marcarles discapacidad o enfermedad catastrófica, o borrar los que había
| registrado Talento Humano.
|
| El 2026-10-19 la ruta volvió con otro contrato
| (docs/diseno-cargas-familiares-portal.md): el titular lee sus cargas y
| presenta solicitudes, que no cuentan hasta que las valida Talento Humano
| (SolicitudesCargaFamiliarTest). Lo que estas pruebas cuidan sigue en pie:
| por el Portal nadie crea, edita ni borra una carga directamente.
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

test('el titular no se da de alta familiares directamente por API', function () {
    $datos = [
        'cedula' => '0805555553', 'nombres' => 'Teo', 'apellidos' => 'Portal',
        'parentesco' => 'hijo', 'fecha_nacimiento' => '2018-01-01', 'genero' => 'masculino',
        'persona_con_discapacidad' => true, 'posee_enfermedad_catastrofica' => false,
    ];
    $this->actingAs($this->usuario, 'sanctum');

    expect($this->postJson('/api/v1/autoservicio/mis-cargas-familiares', $datos)->status())->toBeIn([404, 405]);

    // Por la solicitud sí entra, pero como solicitud: la carga no existe
    // hasta que la valide Talento Humano, y la discapacidad no viaja.
    $this->post('/api/v1/autoservicio/mis-cargas-familiares/solicitudes', [
        ...$datos,
        'tipo'      => 'alta',
        'documento' => \Illuminate\Http\UploadedFile::fake()->create('partida.pdf', 50, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();

    expect(CargaFamiliar::where('cedula', '0805555553')->exists())->toBeFalse();
});

test('el titular no edita ni borra los familiares que registró Talento Humano', function () {
    $url = "/api/v1/autoservicio/mis-cargas-familiares/{$this->registradaPorTh->id}";
    $this->actingAs($this->usuario, 'sanctum');

    $this->putJson($url, ['nombres' => 'Otra', 'posee_enfermedad_catastrofica' => true])
        ->assertNotFound();
    $this->deleteJson($url)->assertNotFound();

    // Leerlas sí: es «Mi perfil».
    $this->getJson('/api/v1/autoservicio/mis-cargas-familiares')
        ->assertOk()
        ->assertJsonPath('datos.cargas.0.nombres', 'Eva');

    expect($this->registradaPorTh->fresh())
        ->not->toBeNull()
        ->nombres->toBe('Eva')
        ->posee_enfermedad_catastrofica->toBeFalse();
});
