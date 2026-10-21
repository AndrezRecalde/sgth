<?php

use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\Triaje;
use App\Models\Expediente\Servidor;
use App\Enums\RegimenLaboral;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Abrir la ficha pasa el turno a «en consulta», y de ahí solo salía al guardar
 * la consulta. Un «Finalizar» sin consulta lo dejaba en consulta para siempre:
 * el cierre nocturno no toca esos turnos.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $rol = Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']);
    $usuario = fn (string $alias) => tap(User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]))->assignRole($rol);

    $this->medico = $usuario('devolver-medico');
    $this->colega = $usuario('devolver-colega');

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Devoluciones']);
    $puesto = puestoDePrueba($unidad, 'Analista de Devoluciones');
    $this->paciente = Servidor::create([
        'cedula' => '0812340500', 'nombre' => 'Paciente', 'apellido' => 'Devuelto',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
});

function turnoAbiertoEnConsulta(User $medico): AgendaMedica
{
    return AgendaMedica::create([
        'folio' => 'TUR-2026-' . random_int(10000, 99999), 'tipo_atencion' => 'medicina_general',
        'servidor_id' => test()->paciente->id, 'medico_id' => $medico->id, 'fecha' => now()->toDateString(),
        'estado' => 'en_consulta', 'requiere_triaje' => false, 'registrado_en' => now(),
    ]);
}

test('sin_consulta_y_sin_triaje_vuelve_a_la_espera', function () {
    $turno = turnoAbiertoEnConsulta($this->medico);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/agenda/{$turno->id}/devolver-a-la-cola")
        ->assertOk();

    expect($turno->fresh()->estado)->toBe('en_espera');
});

test('con_triaje_vuelve_a_la_sala', function () {
    $turno = turnoAbiertoEnConsulta($this->medico);
    $historia = HistoriaClinica::create([
        'numero_historia' => '0912340099', 'cedula_paciente' => '0912340099',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    Triaje::create([
        'agenda_medica_id' => $turno->id, 'historia_clinica_id' => $historia->id,
        'enfermera_id' => $this->medico->id, 'presion_sistolica' => 120, 'presion_diastolica' => 80,
        'frecuencia_cardiaca' => 70, 'frecuencia_respiratoria' => 16, 'temperatura_c' => 36.5,
        'saturacion_oxigeno' => 98, 'peso_kg' => 70, 'talla_cm' => 170, 'registrado_en' => now(),
    ]);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/agenda/{$turno->id}/devolver-a-la-cola")
        ->assertOk();

    expect($turno->fresh()->estado)->toBe('en_sala');
});

test('un_turno_con_consulta_no_vuelve_a_la_cola', function () {
    $turno = turnoAbiertoEnConsulta($this->medico);
    $historia = HistoriaClinica::create([
        'numero_historia' => '0912340098', 'cedula_paciente' => '0912340098',
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    ConsultaMedica::create([
        'especialidad' => 'medicina_general', 'historia_clinica_id' => $historia->id,
        'agenda_medica_id' => $turno->id, 'medico_id' => $this->medico->id,
        'fecha_consulta' => now(), 'hora_consulta' => '09:00', 'motivo_consulta' => 'Control',
    ]);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/agenda/{$turno->id}/devolver-a-la-cola")
        ->assertStatus(422);

    expect($turno->fresh()->estado)->toBe('en_consulta');
});

test('el_turno_de_otro_profesional_no_se_devuelve', function () {
    $turno = turnoAbiertoEnConsulta($this->colega);

    $this->actingAs($this->medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/agenda/{$turno->id}/devolver-a-la-cola")
        ->assertStatus(422);

    expect($turno->fresh()->estado)->toBe('en_consulta');
});
