<?php

use App\Enums\RegimenLaboral;
use App\Models\Dispensario\AgendaMedica;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/*
 * Registrar una consulta no comprobaba nada del turno: se podía registrar una
 * segunda sobre un turno ya atendido, cerrar el turno de otro profesional o
 * colgar la consulta de la historia de otro paciente. Y el alta no pedía el
 * rol clínico: una enfermera podía crear una consulta firmada con su usuario.
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Model::unguard();

    $rol = fn (string $nombre) => Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
    $usuario = fn (string $alias) => User::create([
        'email' => "{$alias}@example.com", 'usuario_ti' => $alias,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);

    $this->medico = $usuario('turno-propio-medico');
    $this->medico->assignRole($rol('medico'));
    $this->colega = $usuario('turno-propio-colega');
    $this->colega->assignRole($rol('medico'));
    $this->enfermera = $usuario('turno-propio-enfermera');
    $this->enfermera->assignRole($rol('enfermera'));

    $unidad = unidadDePrueba(['nombre' => 'Direccion de Turnos Propios']);
    $puesto = puestoDePrueba($unidad, 'Analista de Turnos Propios');
    $servidor = fn (string $cedula, string $nombre) => Servidor::create([
        'cedula' => $cedula, 'nombre' => $nombre, 'apellido' => 'Prueba',
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);

    $this->paciente = $servidor('0812340001', 'Lucia');
    $this->otroPaciente = $servidor('0812340002', 'Pedro');

    $historia = fn (Servidor $s) => HistoriaClinica::create([
        'numero_historia' => $s->cedula, 'cedula_paciente' => $s->cedula,
        'tipo_paciente' => 'servidor', 'servidor_id' => $s->id, 'estado' => true,
    ]);
    $this->historia = $historia($this->paciente);
    $this->historiaAjena = $historia($this->otroPaciente);
});

function turnoDePrueba(Servidor $paciente, User $medico, string $estado = 'en_consulta'): AgendaMedica
{
    return AgendaMedica::create([
        'folio' => 'TUR-2026-' . random_int(10000, 99999), 'tipo_atencion' => 'medicina_general',
        'servidor_id' => $paciente->id, 'medico_id' => $medico->id, 'fecha' => now()->toDateString(),
        'estado' => $estado, 'requiere_triaje' => false, 'registrado_en' => now(),
    ]);
}

function consultaSobreTurno(int $historiaId, int $agendaId): array
{
    return [
        'historia_clinica_id' => $historiaId, 'agenda_medica_id' => $agendaId,
        'fecha_consulta' => now()->toDateString(), 'hora_consulta' => now()->format('H:i'),
        'tipo_atencion' => 'primera_vez', 'tipo_diagnostico' => 'presuntivo',
        'motivo_consulta' => 'Cefalea', 'diagnostico_detallado' => 'Cefalea tensional',
    ];
}

test('quien_atiende_registra_la_consulta_de_su_turno', function () {
    $turno = turnoDePrueba($this->paciente, $this->medico);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertCreated();

    expect($turno->fresh()->estado)->toBe('atendido');
});

test('una_enfermera_no_registra_consultas', function () {
    $turno = turnoDePrueba($this->paciente, $this->medico);

    $this->actingAs($this->enfermera, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertForbidden();

    expect(ConsultaMedica::count())->toBe(0);
});

test('no_se_registra_una_segunda_consulta_sobre_el_mismo_turno', function () {
    $turno = turnoDePrueba($this->paciente, $this->medico);
    $this->actingAs($this->medico, 'sanctum');

    $this->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertCreated();

    // El doble clic, la segunda pestaña o el formulario de alta que la ficha
    // enseñaba mientras cargaba la consulta existente.
    $this->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn ($m) => str_contains($m, 'ya tiene su consulta registrada'));

    expect(ConsultaMedica::where('agenda_medica_id', $turno->id)->count())->toBe(1);
});

test('no_se_registra_la_consulta_en_el_turno_de_otro_profesional', function () {
    $turno = turnoDePrueba($this->paciente, $this->colega);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertStatus(422);

    expect($turno->fresh()->estado)->toBe('en_consulta');
});

test('un_turno_cancelado_no_vuelve_a_atendido', function () {
    $turno = turnoDePrueba($this->paciente, $this->medico, 'cancelada');

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historia->id, $turno->id))
        ->assertStatus(422);

    expect($turno->fresh()->estado)->toBe('cancelada');
});

test('la_consulta_no_cuelga_de_la_historia_de_otro_paciente', function () {
    $turno = turnoDePrueba($this->paciente, $this->medico);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($this->historiaAjena->id, $turno->id))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La historia clínica no es la del paciente de este turno.');
});

test('vale_la_historia_del_preocupacional_que_esta_a_nombre_de_su_cedula', function () {
    $this->historia->forceDelete();
    $preocupacional = HistoriaClinica::create([
        'numero_historia' => 'P-0812340001', 'cedula_paciente' => $this->paciente->cedula,
        'tipo_paciente' => 'candidato', 'estado' => true,
    ]);
    $turno = turnoDePrueba($this->paciente, $this->medico);

    $this->actingAs($this->medico, 'sanctum')
        ->postJson('/api/v1/dispensario/consultas', consultaSobreTurno($preocupacional->id, $turno->id))
        ->assertCreated();
});
