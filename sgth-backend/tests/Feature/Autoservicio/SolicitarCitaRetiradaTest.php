<?php

/*
| `POST /api/v1/autoservicio/solicitar-cita` se retiró.
|
| Nunca funcionó: insertaba en `agendas_medicas` las columnas `fecha_hora` y
| `sintomas`, que no existen, y no mandaba el médico, que es obligatorio. Toda
| llamada respondía 500 «Error interno del servidor.» sin dejar nada en la base.
| Ninguna pantalla la usaba.
|
| Y si alguien hubiera arreglado solo la cita, el permiso por enfermedad que
| creaba a continuación habría nacido sin folio y sin las reglas de
| `PermisoService`: Recepción confirma por folio, así que ese permiso habría
| terminado siempre como falta injustificada.
|
| Las citas las registra el dispensario al admitir al paciente
| (`AgendaService::agendarCita`), y la ausencia la justifica el certificado
| médico, que ya crea su permiso activo y con folio.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba();

    $servidor = Servidor::create([
        'cedula'                   => '0800005101',
        'nombre'                   => 'María',
        'apellido'                 => 'Cita',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->usuario = User::create([
        'email'        => 'maria-cita@example.com',
        'usuario_ti'   => 'mcita',
        'password'     => bcrypt('123456'),
        'primer_login' => false,
        'servidor_id'  => $servidor->id,
    ]);
    $this->usuario->assignRole('servidor');
});

test('la ruta ya no existe: responde 404 y no deja ni cita ni permiso', function () {
    $respuesta = $this->actingAs($this->usuario, 'sanctum')->postJson('/api/v1/autoservicio/solicitar-cita', [
        'fecha_hora' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
        'sintomas'   => 'Dolor de cabeza',
    ]);

    // Antes: 500 «Error interno del servidor.».
    $respuesta->assertNotFound();

    expect(DB::table('agendas_medicas')->count())->toBe(0)
        ->and(PermisoServidor::count())->toBe(0);
});

test('el resto del autoservicio sigue en su sitio', function () {
    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-permisos')
        ->assertOk();

    $this->actingAs($this->usuario, 'sanctum')
        ->getJson('/api/v1/autoservicio/mi-historia-clinica')
        ->assertOk();
});
