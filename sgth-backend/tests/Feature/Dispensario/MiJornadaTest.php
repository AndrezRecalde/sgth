<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| «Mi jornada»: cada profesional ve lo suyo y nada de los demás.
*/

function usuarioMiJornada(string $rol): User
{
    $u = User::factory()->create();
    $u->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $u;
}

function turnoMiJornada(User $medico, int $servidorId, string $estado, bool $requiereTriaje = false): int
{
    static $n = 0;
    $n++;

    return DB::table('agendas_medicas')->insertGetId([
        'folio' => "MJ-{$n}", 'medico_id' => $medico->id, 'servidor_id' => $servidorId,
        'fecha' => Carbon::today()->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '08:30',
        'estado' => $estado, 'tipo_atencion' => 'medicina_general', 'requiere_triaje' => $requiereTriaje,
        'registrado_en' => now(), 'estado_registro' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    Servidor::unguard();
    $this->paciente = Servidor::create(['cedula' => '0802000001', 'nombre' => 'Ana', 'apellido' => 'Mora', 'estado' => true]);
    $this->historia = HistoriaClinica::create([
        'numero_historia' => '0802000001', 'cedula_paciente' => '0802000001',
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->paciente->id, 'estado' => true,
    ]);
    $this->medico = usuarioMiJornada('medico');
    $this->otroMedico = usuarioMiJornada('medico');
});

test('el médico ve sus turnos de hoy, y no los de otro', function () {
    turnoMiJornada($this->medico, $this->paciente->id, 'en_espera');                   // listo: sin triaje
    turnoMiJornada($this->medico, $this->paciente->id, 'en_espera', true);             // espera triaje
    turnoMiJornada($this->medico, $this->paciente->id, 'atendido');
    turnoMiJornada($this->otroMedico, $this->paciente->id, 'en_espera');               // de otro

    $hoy = $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/mi-jornada')
        ->assertOk()
        ->json('datos');

    expect($hoy['perfil'])->toBe('medico')
        ->and($hoy['hoy']['esperando'])->toBe(2)
        ->and($hoy['hoy']['listos'])->toBe(1)
        ->and($hoy['hoy']['atendidos'])->toBe(1);
});

test('las consultas empezadas y sin cerrar cuentan como pendientes', function () {
    $abierto = turnoMiJornada($this->medico, $this->paciente->id, 'en_consulta');
    $cerrado = turnoMiJornada($this->medico, $this->paciente->id, 'atendido');
    foreach ([$abierto, $cerrado] as $agenda) {
        DB::table('borradores_consulta')->insert([
            'agenda_medica_id' => $agenda, 'medico_id' => $this->medico->id, 'contenido' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $datos = $this->actingAs($this->medico, 'sanctum')->getJson('/api/v1/dispensario/mi-jornada')->json('datos');

    expect($datos['pendientes']['borradores'])->toBe(1);
});

test('el mes del médico cuenta sus consultas y pacientes distintos', function () {
    ConsultaMedica::unguard();
    foreach ([$this->medico, $this->medico, $this->otroMedico] as $m) {
        ConsultaMedica::create([
            'historia_clinica_id' => $this->historia->id, 'medico_id' => $m->id, 'especialidad' => 'medicina_general',
            'fecha_consulta' => now()->toDateString(), 'hora_consulta' => '09:00:00',
            'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Sin hallazgos',
        ]);
    }

    $mes = $this->actingAs($this->medico, 'sanctum')->getJson('/api/v1/dispensario/mi-jornada')->json('datos.mes');

    expect($mes['consultas'])->toBe(2)
        ->and($mes['pacientes'])->toBe(1);
});

test('enfermería ve la cola de triaje del equipo y sus propias atenciones', function () {
    $enfermera = usuarioMiJornada('enfermera');
    $otra = usuarioMiJornada('enfermera');
    turnoMiJornada($this->medico, $this->paciente->id, 'en_espera', true);   // por triar
    turnoMiJornada($this->medico, $this->paciente->id, 'en_espera', false);  // no necesita
    $servicio = DB::table('catalogo_servicios_enfermeria')->insertGetId(['nombre' => 'Curación', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    foreach ([$enfermera, $enfermera, $otra] as $e) {
        DB::table('atenciones_enfermeria')->insert([
            'enfermera_id' => $e->id, 'servidor_id' => $this->paciente->id, 'catalogo_servicio_id' => $servicio,
            'atendido_en' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $datos = $this->actingAs($enfermera, 'sanctum')->getJson('/api/v1/dispensario/mi-jornada')->json('datos');

    expect($datos['perfil'])->toBe('enfermeria')
        ->and($datos['hoy']['por_triar'])->toBe(1)
        ->and($datos['hoy']['mis_atenciones'])->toBe(2)
        ->and($datos['mes']['por_servicio'][0])->toBe(['servicio' => 'Curación', 'total' => 2]);
});

test('el odontólogo cuenta sus procedimientos del mes', function () {
    $odontologo = usuarioMiJornada('odontologo');

    $datos = $this->actingAs($odontologo, 'sanctum')->getJson('/api/v1/dispensario/mi-jornada')->json('datos');

    expect($datos['perfil'])->toBe('odontologo')
        ->and($datos['mes'])->toHaveKey('procedimientos')
        ->and($datos['pendientes']['fichas_femo'])->toBe(0);
});

test('quien no atiende pacientes no tiene jornada', function () {
    $this->actingAs(usuarioMiJornada('admin-uath'), 'sanctum')
        ->getJson('/api/v1/dispensario/mi-jornada')
        ->assertStatus(403);
});

test('con dos roles, la jornada es la de la pantalla desde la que se pide', function () {
    $ambos = usuarioMiJornada('medico');
    $ambos->assignRole(Role::firstOrCreate(['name' => 'enfermera', 'guard_name' => 'sanctum']));

    $this->actingAs($ambos, 'sanctum');
    expect($this->getJson('/api/v1/dispensario/mi-jornada?perfil=enfermeria')->json('datos.perfil'))->toBe('enfermeria')
        ->and($this->getJson('/api/v1/dispensario/mi-jornada?perfil=clinico')->json('datos.perfil'))->toBe('medico');

    // Pedir un perfil que no tiene no le da otro: se queda con el suyo.
    expect($this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/mi-jornada?perfil=enfermeria')->json('datos.perfil'))->toBe('medico');
});
