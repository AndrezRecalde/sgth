<?php

/*
| Los reposos médicos en el ausentismo por enfermedad y en el portal
| (decisiones del 2026-10-08):
|
| - Desde que el reposo es un certificado y no un permiso, los indicadores
|   (Consolidado, Riesgos Laborales › Ausentismo, tablero de SSO y panel del
|   Expediente) lo suman aparte de los permisos por enfermedad.
| - Solo los aprobados por TH o Trabajo Social, y no anulados.
| - En días calendario: cada día del reposo que cae en el período, fin de
|   semana incluido, vale una jornada.
| - El servidor ve sus reposos en «Mis permisos», sin el diagnóstico.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Sso\DashboardSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    ConsultaMedica::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección Reposos']);
    // Sin marcación: en enfermedad el consolidado no filtra por ella.
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($this->unidad)->id, 'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true, 'puede_marcar' => false,
    ]);

    $this->uath = User::create([
        'email' => 'reposos@example.com', 'usuario_ti' => 'reposos_uath',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');
});

/** Un reposo del servidor, aprobado salvo que se diga otra cosa. */
function reposoAusentismo(Servidor $s, string $desde, string $hasta, array $extra = []): CertificadoMedico
{
    static $n = 0;
    $n++;

    $medico = User::factory()->create();
    $historia = HistoriaClinica::firstOrCreate(['cedula_paciente' => $s->cedula], [
        'numero_historia' => "HC-REP-{$n}", 'tipo_paciente' => 'servidor', 'servidor_id' => $s->id, 'estado' => true,
    ]);
    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id, 'medico_id' => $medico->id, 'especialidad' => 'medicina_general',
        'fecha_consulta' => $desde, 'hora_consulta' => '09:00:00', 'motivo_consulta' => 'Control',
    ]);

    $id = DB::table('certificados_medicos')->insertGetId(array_merge([
        'consulta_medica_id' => $consulta->id, 'servidor_id' => $s->id, 'emitido_por' => $medico->id,
        'dias_reposo' => 3, 'fecha_inicio' => $desde, 'fecha_fin' => $hasta,
        'observaciones' => 'Lumbalgia aguda', 'folio' => sprintf('CERT-2026-%05d', 98000 + $n),
        'tipo_paciente' => 'servidor', 'aprobado_en' => '2026-11-02 12:00:00', 'registro_sirha7' => 'sgth',
        'sirha7_leave_nombre' => 'DISPENSARIO MÉDICO GADPE', 'created_at' => now(), 'updated_at' => now(),
    ], $extra));

    return CertificadoMedico::findOrFail($id);
}

function consolidadoEnfermedadReposos(string $desde, string $hasta): array
{
    return test()->actingAs(test()->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/consolidado-permisos?' . http_build_query([
            'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'tipo' => 'enfermedad',
        ]))->assertOk()->json('datos');
}

// ── Consolidado ──────────────────────────────────────────────────────

test('el consolidado de enfermedad suma los reposos aprobados en días calendario', function () {
    // Jueves 19 al sábado 21: tres días, el sábado incluido.
    reposoAusentismo($this->servidor, '2026-11-19', '2026-11-21');

    $datos = consolidadoEnfermedadReposos('2026-11-01', '2026-11-30');

    expect($datos['consolidado'])->toHaveCount(1)
        ->and($datos['consolidado'][0]['total_permisos'])->toBe(1)
        ->and($datos['consolidado'][0]['total_minutos'])->toBe(3 * 480)
        ->and($datos['consolidado'][0]['total_dias'])->toEqual(3)
        ->and($datos['totales']['total_dias'])->toEqual(3);
});

test('solo cuentan los días del reposo que caen en el período', function () {
    // Del jueves 29/10 al sábado 31/10 y del sábado 31/10 al lunes 02/11.
    reposoAusentismo($this->servidor, '2026-10-29', '2026-10-31');
    reposoAusentismo($this->servidor, '2026-11-01', '2026-11-02', ['dias_reposo' => 2]);

    $noviembre = consolidadoEnfermedadReposos('2026-11-01', '2026-11-30');

    expect($noviembre['consolidado'][0]['total_permisos'])->toBe(1)
        ->and($noviembre['consolidado'][0]['total_dias'])->toEqual(2);

    $cruzado = consolidadoEnfermedadReposos('2026-10-30', '2026-11-01');

    expect($cruzado['consolidado'][0]['total_permisos'])->toBe(2)
        ->and($cruzado['consolidado'][0]['total_dias'])->toEqual(3);
});

test('los pendientes y los anulados no cuentan; los permisos por enfermedad siguen contando', function () {
    reposoAusentismo($this->servidor, '2026-11-03', '2026-11-05', ['aprobado_en' => null, 'registro_sirha7' => null]);
    reposoAusentismo($this->servidor, '2026-11-10', '2026-11-12', ['anulado_en' => '2026-11-10 10:00:00']);
    PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-11-16',
        'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'estado' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
        'folio' => 'PER-2026-98001', 'vence_en' => '2026-11-18',
    ]);

    $datos = consolidadoEnfermedadReposos('2026-11-01', '2026-11-30');

    expect($datos['consolidado'][0]['total_permisos'])->toBe(1)
        ->and($datos['consolidado'][0]['total_minutos'])->toBe(120);
});

test('los reposos no entran en el consolidado de otros tipos', function () {
    reposoAusentismo($this->servidor, '2026-11-19', '2026-11-21');

    $personal = $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/consolidado-permisos?' . http_build_query([
            'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-30', 'tipo' => 'personal',
        ]))->assertOk()->json('datos.consolidado');

    expect($personal)->toBe([]);
});

// ── Tablero de SSO y panel del Expediente ────────────────────────────

test('el tablero de SSO cuenta el reposo como el consolidado', function () {
    reposoAusentismo($this->servidor, '2026-11-19', '2026-11-21');
    PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-11-25',
        'hora_inicio' => '08:00', 'hora_fin' => '12:00', 'estado' => EstadoPermiso::ACTIVO->value,
        'folio' => 'PER-2026-98002', 'vence_en' => '2026-11-27',
    ]);

    $ausentismo = app(DashboardSsoService::class)->resumen('2026')['ausentismo'];

    expect($ausentismo['total_permisos'])->toBe(2)
        ->and($ausentismo['servidores_afectados'])->toBe(1)
        ->and($ausentismo['total_dias'])->toEqual(3.5);
});

test('el panel del Expediente cuenta los reposos aprobados de los últimos doce meses', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-12-01 09:00'));
    reposoAusentismo($this->servidor, '2026-11-19', '2026-11-21');
    reposoAusentismo($this->servidor, '2026-11-24', '2026-11-25', ['aprobado_en' => null, 'dias_reposo' => 2]);
    reposoAusentismo($this->servidor, '2025-10-01', '2025-10-02', ['dias_reposo' => 2]);

    $datos = $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/expediente/servidores/{$this->servidor->id}/ausentismo-salud")
        ->assertOk()->json('datos');

    expect($datos['reposos'])->toBe(1)
        ->and($datos['dias_reposo'])->toBe(3)
        ->and($datos['permisos'])->toBe(0);
});

// ── «Mis permisos» ───────────────────────────────────────────────────

test('el servidor ve sus reposos y su estado, sin el diagnóstico; los de otro no', function () {
    $propio = User::create([
        'email' => 'reposos-propio@example.com', 'usuario_ti' => 'reposos_propio',
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $this->servidor->id,
    ]);
    $propio->assignRole('servidor');

    $otro = Servidor::create([
        'cedula' => '0912345678', 'nombre' => 'Ana', 'apellido' => 'Vera',
        'puesto_id' => puestoDePrueba($this->unidad)->id, 'unidad_administrativa_id' => $this->unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    reposoAusentismo($otro, '2026-11-19', '2026-11-21');

    $aprobado = reposoAusentismo($this->servidor, '2026-11-19', '2026-11-21');
    $pendiente = reposoAusentismo($this->servidor, '2026-11-24', '2026-11-25', ['aprobado_en' => null, 'registro_sirha7' => null]);

    $filas = $this->actingAs($propio, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-certificados-medicos')->assertOk()->json('datos.data');

    expect(array_column($filas, 'folio'))->toBe([$pendiente->folio, $aprobado->folio])
        ->and(array_column($filas, 'estado'))->toBe(['pendiente', 'aprobado'])
        ->and($filas[1]['sirha7_leave_nombre'])->toBe('DISPENSARIO MÉDICO GADPE')
        ->and(array_keys($filas[0]))->toEqualCanonicalizing([
            'id', 'folio', 'fecha_inicio', 'fecha_fin', 'dias_reposo', 'emitido_en', 'estado',
            'registro_sirha7', 'sirha7_leave_nombre',
        ]);
});
