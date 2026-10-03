<?php

use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\DiagnosticoCie10;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El panorama del Dispensario: lo que el tablero no veía (flujo del día,
| enfermería, reposos, salud ocupacional y tendencia).
*/

beforeEach(function () {
    Servidor::unguard();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin-dispensario', 'guard_name' => 'sanctum']));
    $this->medico = User::factory()->create();
    $this->paciente = Servidor::create(['cedula' => '0801000001', 'nombre' => 'Ana', 'apellido' => 'Mora', 'estado' => true]);
    $this->historia = HistoriaClinica::create([
        'numero_historia' => '0801000001', 'cedula_paciente' => '0801000001',
        'tipo_paciente' => 'servidor', 'servidor_id' => $this->paciente->id, 'estado' => true,
    ]);
});

function turnoPanorama($test, string $estado, ?Carbon $llegada = null, string $fecha = null): int
{
    static $n = 0;
    $n++;

    return DB::table('agendas_medicas')->insertGetId([
        'folio' => "T-{$n}", 'medico_id' => $test->medico->id, 'servidor_id' => $test->paciente->id,
        'fecha' => $fecha ?? Carbon::today()->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '08:30',
        'estado' => $estado, 'tipo_atencion' => 'medicina_general', 'requiere_triaje' => false,
        'registrado_en' => $llegada, 'estado_registro' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function consultaPanorama($test, string $especialidad, Carbon $creada, ?int $agendaId = null): ConsultaMedica
{
    ConsultaMedica::unguard();
    $c = ConsultaMedica::create([
        'historia_clinica_id' => $test->historia->id, 'medico_id' => $test->medico->id,
        'agenda_medica_id' => $agendaId, 'especialidad' => $especialidad,
        'fecha_consulta' => $creada->toDateString(), 'hora_consulta' => $creada->format('H:i:s'),
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Sin hallazgos',
    ]);
    $c->forceFill(['created_at' => $creada])->saveQuietly();

    return $c;
}

function panoramaDispensario($test, string $query = ''): array
{
    return $test->actingAs($test->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/dashboard/panorama'.$query)
        ->assertOk()
        ->json('datos');
}

test('el flujo de hoy cuenta los turnos por estado y la espera desde la llegada', function () {
    $llegada = Carbon::now()->subMinutes(50);
    $atendido = turnoPanorama($this, 'atendido', $llegada);
    consultaPanorama($this, 'medicina_general', $llegada->copy()->addMinutes(30), $atendido);
    turnoPanorama($this, 'en_espera', Carbon::now()->subMinutes(5));
    turnoPanorama($this, 'en_espera', Carbon::now()->subMinutes(2));
    turnoPanorama($this, 'no_presentado');
    // De ayer: no es del flujo de hoy.
    turnoPanorama($this, 'en_espera', null, Carbon::yesterday()->toDateString());

    $flujo = panoramaDispensario($this)['flujo_hoy'];

    expect($flujo['total'])->toBe(4)
        ->and($flujo['por_estado']['en_espera'])->toBe(2)
        ->and($flujo['por_estado']['atendido'])->toBe(1)
        ->and($flujo['por_estado']['no_presentado'])->toBe(1)
        ->and($flujo['espera_promedio_min'])->toBe(30);
});

test('enfermería cuenta por servicio y deja fuera lo anulado', function () {
    $curacion = DB::table('catalogo_servicios_enfermeria')->insertGetId(['nombre' => 'Curación', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $presion = DB::table('catalogo_servicios_enfermeria')->insertGetId(['nombre' => 'Toma de presión', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
    $fila = fn (int $servicio, ?Carbon $anulada = null) => DB::table('atenciones_enfermeria')->insert([
        'enfermera_id' => $this->medico->id, 'servidor_id' => $this->paciente->id,
        'catalogo_servicio_id' => $servicio, 'atendido_en' => now(), 'anulado_en' => $anulada,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $fila($curacion);
    $fila($curacion);
    $fila($presion);
    $fila($presion, now());

    $enfermeria = panoramaDispensario($this)['enfermeria'];

    expect($enfermeria['total'])->toBe(3)
        ->and($enfermeria['por_servicio'][0])->toBe(['servicio' => 'Curación', 'total' => 2])
        ->and($enfermeria['por_servicio'][1])->toBe(['servicio' => 'Toma de presión', 'total' => 1]);
});

test('los reposos suman días por diagnóstico y no cuentan los anulados', function () {
    $lumbalgia = DiagnosticoCie10::create(['codigo' => 'M545', 'descripcion' => 'LUMBAGO NO ESPECIFICADO', 'categoria' => 'M54', 'activo' => true]);
    $consulta = consultaPanorama($this, 'medicina_general', now());
    $reposo = fn (int $dias, ?Carbon $anulado = null) => DB::table('certificados_medicos')->insert([
        'consulta_medica_id' => $consulta->id, 'emitido_por' => $this->medico->id, 'dias_reposo' => $dias,
        'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addDays($dias)->toDateString(),
        'diagnostico_cie10_id' => $lumbalgia->id, 'tipo_paciente' => 'servidor', 'anulado_en' => $anulado,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $reposo(3);
    $reposo(2);
    $reposo(10, now());

    $reposos = panoramaDispensario($this)['reposos'];

    expect($reposos['certificados'])->toBe(2)
        ->and($reposos['dias'])->toBe(5)
        ->and($reposos['diagnosticos'][0])->toBe(['codigo' => 'M545', 'descripcion' => 'LUMBAGO NO ESPECIFICADO', 'total' => 2, 'dias' => 5]);
});

test('la tendencia trae doce meses, con cero en los que no hubo atenciones', function () {
    consultaPanorama($this, 'medicina_general', Carbon::parse('2026-09-10 10:00'));
    consultaPanorama($this, 'odontologia', Carbon::parse('2026-09-12 10:00'));
    consultaPanorama($this, 'odontologia', Carbon::parse('2026-04-01 10:00'));

    $tendencia = panoramaDispensario($this, '?desde=2026-09-01&hasta=2026-09-30')['tendencia'];

    expect($tendencia)->toHaveCount(12)
        ->and($tendencia[0]['mes'])->toBe('2025-10')
        ->and($tendencia[11])->toBe(['mes' => '2026-09', 'medicina_general' => 1, 'odontologia' => 1])
        ->and(collect($tendencia)->firstWhere('mes', '2026-04')['odontologia'])->toBe(1);
});

test('la salud ocupacional llega resumida', function () {
    $s = panoramaDispensario($this)['salud_ocupacional'];

    expect($s)->toHaveKeys(['por_atender', 'vencidas', 'retiros', 'cobertura_vencida', 'cobertura_sin_evaluacion', 'plantilla']);
});

test('el panorama es de la administración del dispensario', function () {
    $medico = User::factory()->create();
    $medico->assignRole(Role::firstOrCreate(['name' => 'medico', 'guard_name' => 'sanctum']));

    $this->actingAs($medico, 'sanctum')
        ->getJson('/api/v1/dispensario/dashboard/panorama')
        ->assertStatus(403);
});
