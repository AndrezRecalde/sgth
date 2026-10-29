<?php

/*
| La agenda y la cola de triaje con lo justo del paciente (revisión de las
| cuentas de prueba, 2026-10-29).
|
| Cargaban `servidor` y `cargaFamiliar.servidor` sin columnas, y `Servidor` no
| oculta nada: a todo el que abría la agenda le llegaba la ficha entera —cédula,
| tipo de sangre, celular, correo personal, dirección, contacto de
| emergencia—. Y la cola de triaje, de todos los médicos, la veía cualquiera con
| la agenda.
*/

use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Lo que la ficha del paciente no debe soltar en la agenda. */
const DATOS_DE_LA_FICHA = [
    'cedula', 'tipo_sangre', 'telefono_celular', 'correo_personal', 'direccion_domicilio',
    'pasaporte_numero', 'tiene_discapacidad', 'tiene_enfermedad_catastrofica',
    'contacto_emergencia_nombre', 'contacto_emergencia_telefono',
];

function usuarioAgendaSinFicha(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function turnoAgendaSinFicha(User $medico, array $paciente, string $folio): string
{
    DB::table('agendas_medicas')->insert([
        ...$paciente,
        'folio' => $folio, 'medico_id' => $medico->id,
        'fecha' => Carbon::today()->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '08:30',
        'estado' => 'en_espera', 'tipo_atencion' => 'medicina_general', 'requiere_triaje' => true,
        'registrado_en' => now(), 'estado_registro' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $folio;
}

/** Que el paciente traiga su nombre y su nacimiento, y nada de su ficha. */
function expectPacienteRecortado(?array $paciente, string $nombre): void
{
    expect($paciente)->not->toBeNull()
        ->and($paciente['nombre'] ?? $paciente['nombres'])->toBe($nombre)
        ->and($paciente)->toHaveKey('fecha_nacimiento');

    foreach (DATOS_DE_LA_FICHA as $campo) {
        expect($paciente)->not->toHaveKey($campo);
    }
}

beforeEach(function () {
    Servidor::unguard();
    $this->titular = Servidor::create([
        'cedula' => '0812700001', 'nombre' => 'Marta', 'apellido' => 'Quiñónez', 'estado' => true,
        'fecha_nacimiento' => '1985-04-02', 'tipo_sangre' => 'O+', 'telefono_celular' => '0991112233',
        'correo_personal' => 'marta@example.com', 'direccion_domicilio' => 'Calle 10 de Agosto',
        'contacto_emergencia_nombre' => 'Luis Quiñónez', 'contacto_emergencia_telefono' => '0994445566',
    ]);
    $this->hija = CargaFamiliar::create([
        'servidor_id' => $this->titular->id, 'cedula' => '0812700002', 'nombres' => 'Lía',
        'apellidos' => 'Quiñónez', 'parentesco' => 'hijo', 'fecha_nacimiento' => '2016-05-01',
        'genero' => 'femenino', 'persona_con_discapacidad' => false, 'posee_enfermedad_catastrofica' => false,
    ]);

    $this->medico = usuarioAgendaSinFicha('medico');
    $this->enfermera = usuarioAgendaSinFicha('enfermera');

    $this->folioTitular = turnoAgendaSinFicha($this->medico, ['servidor_id' => $this->titular->id], 'ASF-1');
    // Un turno es de un servidor o de una carga, no de los dos (chk_paciente_exclusivo).
    $this->folioHija = turnoAgendaSinFicha($this->medico, ['carga_familiar_id' => $this->hija->id], 'ASF-2');
});

test('la agenda de Enfermería trae del paciente el nombre y el nacimiento, no su ficha', function () {
    $turnos = collect($this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/agenda?fecha=' . today()->toDateString())
        ->assertOk()->json('datos.data'))->keyBy('folio');

    expectPacienteRecortado($turnos['ASF-1']['servidor'], 'Marta');
    expectPacienteRecortado($turnos['ASF-2']['carga_familiar'], 'Lía');
    expectPacienteRecortado($turnos['ASF-2']['carga_familiar']['servidor'] + ['fecha_nacimiento' => null], 'Marta');
});

test('los turnos del médico y el turno por folio, igual', function () {
    $this->actingAs($this->medico, 'sanctum');

    $delDia = collect($this->getJson('/api/v1/dispensario/agenda/turnos-del-dia')->assertOk()->json('datos'))
        ->keyBy('folio');
    expectPacienteRecortado($delDia['ASF-1']['servidor'], 'Marta');

    $porFolio = $this->getJson('/api/v1/dispensario/agenda/por-folio/ASF-2')->assertOk()->json('datos');
    expectPacienteRecortado($porFolio['carga_familiar'], 'Lía');
});

test('la cola de triaje: toda para quien la lleva, la suya para el médico, y sin la ficha', function () {
    $folios = fn (User $quien) => collect($this->actingAs($quien, 'sanctum')
        ->getJson('/api/v1/dispensario/triaje/pendientes')->assertOk()->json('datos'))
        ->pluck('folio')->sort()->values()->all();

    // Enfermería y la administración llevan la cola de todos.
    expect($folios($this->enfermera))->toBe(['ASF-1', 'ASF-2'])
        ->and($folios(usuarioAgendaSinFicha('admin-dispensario')))->toBe(['ASF-1', 'ASF-2'])
        // El médico, los de sus turnos; otro médico y el odontólogo, ninguno.
        ->and($folios($this->medico))->toBe(['ASF-1', 'ASF-2'])
        ->and($folios(usuarioAgendaSinFicha('medico')))->toBe([])
        ->and($folios(usuarioAgendaSinFicha('odontologo')))->toBe([]);

    $primero = $this->actingAs($this->enfermera, 'sanctum')
        ->getJson('/api/v1/dispensario/triaje/pendientes')->json('datos.0');
    expectPacienteRecortado($primero['servidor'], 'Marta');
});
