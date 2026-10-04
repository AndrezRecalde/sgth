<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Salud ocupacional, gestión de turnos y el informe del Dispensario.
 */

function usuarioTerceraTanda(string $usuario, string $rol): User
{
    $user = User::forceCreate([
        'email' => "{$usuario}@example.com", 'usuario_ti' => $usuario,
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $user->assignRole(Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']));

    return $user;
}

/** Una ficha FEMO con su solicitud, en el estado dado. */
function femoTerceraTanda(int $evaluador, int $solicitante, string $tipo, string $aptitud, string $estadoSolicitud, string $fecha = '2026-09-10'): void
{
    $ficha = DB::table('fichas_salud_ocupacional')->insertGetId([
        'fecha_evaluacion' => $fecha, 'tipo_ficha' => $tipo, 'aptitud' => $aptitud, 'evaluador_id' => $evaluador,
        // La ficha es de alguien: un servidor o un candidato.
        'servidor_id' => DB::table('servidores')->value('id'),
    ]);
    DB::table('solicitudes_certificacion_medica')->insert([
        'tipo_evento' => $tipo, 'cedula_paciente' => uniqid(), 'nombres_paciente' => 'Paciente',
        'solicitado_por' => $solicitante, 'estado' => $estadoSolicitud, 'ficha_femo_id' => $ficha,
        'fecha_limite' => '2026-09-20',
    ]);
}

/** Un turno del día dado, en el estado dado. */
function turnoTerceraTanda(int $medico, string $estado, string $especialidad = 'medicina_general', ?int $esperaMin = null): void
{
    $servidor = DB::table('servidores')->value('id');
    $registrado = '2026-09-10 08:00:00';
    $turno = DB::table('agendas_medicas')->insertGetId([
        'medico_id' => $medico, 'servidor_id' => $servidor, 'fecha' => '2026-09-10',
        'estado' => $estado, 'tipo_atencion' => $especialidad, 'registrado_en' => $registrado,
    ]);

    if ($esperaMin !== null) {
        $historia = DB::table('historias_clinicas')->where('servidor_id', $servidor)->value('id');
        DB::table('consultas_medicas')->insert([
            'historia_clinica_id' => $historia, 'medico_id' => $medico, 'agenda_medica_id' => $turno,
            'fecha_consulta' => '2026-09-10', 'hora_consulta' => '09:00', 'especialidad' => $especialidad,
            'created_at' => date('Y-m-d H:i:s', strtotime($registrado) + $esperaMin * 60),
        ]);
    }
}

beforeEach(function () {
    $this->admin     = usuarioTerceraTanda('admin3', 'admin-dispensario');
    $this->autoridad = usuarioTerceraTanda('aut3', 'maxima-autoridad');
    $this->medico    = usuarioTerceraTanda('med3', 'medico');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Turnos']);
    $servidor = App\Models\Expediente\Servidor::forceCreate([
        'cedula' => '0806666661', 'nombre' => 'Pía', 'apellido' => 'Turnos',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Turnos')->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => App\Enums\RegimenLaboral::LOSEP,
        'fecha_ingreso_institucion' => now()->subYears(2), 'estado' => true,
    ]);
    DB::table('historias_clinicas')->insert([
        'numero_historia' => '0806666661', 'cedula_paciente' => '0806666661',
        'tipo_paciente' => 'servidor', 'servidor_id' => $servidor->id, 'estado' => true,
    ]);

    $this->filtros = '?desde=2026-09-01&hasta=2026-09-30';
});

test('salud ocupacional cuenta solo las fichas cerradas, por tipo y aptitud', function () {
    femoTerceraTanda($this->medico->id, $this->admin->id, 'ingreso', 'apto', 'completada');
    femoTerceraTanda($this->medico->id, $this->admin->id, 'ingreso', 'no_apto', 'completada');
    femoTerceraTanda($this->medico->id, $this->admin->id, 'periodica', 'apto', 'completada');
    // Borrador del médico: no cuenta como evaluación, sí como solicitud abierta.
    femoTerceraTanda($this->medico->id, $this->admin->id, 'periodica', 'apto', 'en_proceso');

    $filas = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/salud_ocupacional' . $this->filtros)
        ->assertOk()->json('datos.filas'))->keyBy('tipo');

    expect($filas['Ingreso'])->toMatchArray(['evaluaciones' => 2, 'apto' => 1, 'no_apto' => 1]);
    expect($filas['Periódico'])->toMatchArray(['evaluaciones' => 1, 'solicitudes_pendientes' => 1]);
    // Venció el 20 de septiembre y hoy sigue abierta.
    expect($filas['Periódico']['solicitudes_vencidas'])->toBe(now()->toDateString() > '2026-09-20' ? 1 : 0);
});

test('la gestión de turnos agrupa por especialidad por defecto, sin contar lo cancelado como ausencia', function () {
    turnoTerceraTanda($this->medico->id, 'atendido', esperaMin: 20);
    turnoTerceraTanda($this->medico->id, 'atendido', esperaMin: 40);
    turnoTerceraTanda($this->medico->id, 'no_presentado');
    turnoTerceraTanda($this->medico->id, 'cancelada');
    turnoTerceraTanda($this->medico->id, 'atendido', 'odontologia');

    $filas = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/turnos' . $this->filtros)
        ->assertOk()->json('datos.filas'))->keyBy('especialidad');

    expect($filas['Medicina general'])->toMatchArray([
        'turnos' => 4, 'atendidos' => 2, 'no_presentados' => 1, 'cancelados' => 1,
        // 2 atendidos de 3 que no se cancelaron.
        'asistencia' => 66.7, 'espera_min' => 30,
    ]);
    expect($filas['Odontología']['turnos'])->toBe(1);

    $this->actingAs($this->medico, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/turnos' . $this->filtros)->assertForbidden();
});

test('el informe junta los demás reportes y sale en PDF de una hoja', function () {
    turnoTerceraTanda($this->medico->id, 'atendido', esperaMin: 15);
    femoTerceraTanda($this->medico->id, $this->admin->id, 'ingreso', 'apto', 'completada');

    $filas = collect($this->actingAs($this->autoridad, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes/informe' . $this->filtros)
        ->assertOk()->json('datos.filas'));

    expect($filas->pluck('seccion')->unique()->values()->all())->toBe([
        'Atención', 'Principales diagnósticos', 'Ausentismo por enfermedad', 'Enfermería',
        'Farmacia', 'Turnos', 'Salud ocupacional',
    ]);
    expect($filas->firstWhere('indicador', 'Consultas de medicina general')['valor'])->toBe(1);
    expect($filas->firstWhere('indicador', 'Espera promedio (min)')['valor'])->toBe(15);
    expect($filas->firstWhere('indicador', 'Evaluaciones FEMO cerradas')['valor'])->toBe(1);

    $pdf = $this->actingAs($this->autoridad, 'sanctum')
        ->get('/api/v1/dispensario/reportes/informe/pdf' . $this->filtros)
        ->assertOk();
    expect($pdf->headers->get('content-type'))->toBe('application/pdf');
    expect(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf->getContent()))->toBe(1);
});

test('solo el informe se descarga en PDF', function () {
    $this->actingAs($this->admin, 'sanctum')
        ->get('/api/v1/dispensario/reportes/morbilidad/pdf' . $this->filtros)->assertNotFound();
});

test('el catálogo agrupa por área y dice en qué formatos sale cada reporte', function () {
    $catalogo = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.reportes'));

    expect($catalogo->pluck('area')->unique()->values()->all())
        ->toBe(['Atención clínica', 'Enfermería', 'Farmacia', 'Salud ocupacional', 'Gestión']);
    expect($catalogo->firstWhere('clave', 'informe')['formatos'])->toBe(['excel', 'pdf']);
    expect($catalogo->firstWhere('clave', 'turnos')['formatos'])->toBe(['excel']);
});

test('cada agrupación que ofrece un reporte se acepta', function () {
    turnoTerceraTanda($this->medico->id, 'atendido');

    $catalogo = collect($this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/dispensario/reportes')->json('datos.reportes'));

    foreach ($catalogo as $reporte) {
        foreach ($reporte['agrupaciones'] as $agrupacion) {
            $this->getJson("/api/v1/dispensario/reportes/{$reporte['clave']}{$this->filtros}&agrupacion={$agrupacion['value']}")
                ->assertOk();
        }
    }

    // La de otro reporte no rompe: se usa la primera propia.
    $this->getJson('/api/v1/dispensario/reportes/turnos' . $this->filtros . '&agrupacion=diagnostico')
        ->assertOk()->assertJsonPath('datos.columnas.0.clave', 'especialidad');
});
