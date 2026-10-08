<?php

/*
| Talento Humano y Trabajo Social aprueban los certificados médicos y los
| registran en Sirha7 (decisiones del 2026-10-08):
|
| - Se aprueba el certificado, no un permiso creado a partir de él.
| - Aprueban admin-uath, asistente-uath y trabajo-social.
| - Solo los del servidor titular; nunca se ve el diagnóstico.
| - Lo que el SGTH no puede escribir en Sirha7 se aprueba sin Sirha7, con nota.
|
| Sirha7 se reemplaza con un doble: el CI no tiene SQL Server. Los
| procedimientos son los de los permisos, probados contra Sirha7 en el PR #378.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoParentesco;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\CertificadoMedico;
use App\Models\Dispensario\CertificadoSirha7Fila;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\AprobacionCertificadoSirha7Service;
use App\Services\Asistencia\Sirha7PermisoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();
    ConsultaMedica::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    config(['services.biometrico.aprobacion_permisos_desde' => '2026-10-01']);

    $unidad = unidadDePrueba(['nombre' => 'Dirección Certificados Sirha7']);
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->uath = usuarioCertAprobacion('admin-uath');
    $this->asistente = usuarioCertAprobacion('asistente-uath');
    $this->ts = usuarioCertAprobacion('trabajo-social');
    $this->recepcion = usuarioCertAprobacion('recepcion');

    $this->sirha7 = $this->mock(Sirha7PermisoService::class);
    $this->sirha7->shouldReceive('tipos')->andReturn([
        ['id' => 13, 'nombre' => 'DISPENSARIO MÉDICO GADPE'],
        ['id' => 14, 'nombre' => 'ENFERMEDAD'],
    ])->byDefault();
});

function usuarioCertAprobacion(string $rol, ?Servidor $servidor = null): User
{
    $u = User::create([
        'email' => uniqid('cert').'@example.com', 'usuario_ti' => uniqid('ct'),
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $u->assignRole($rol);

    return $u;
}

/** Un certificado emitido el 12/10, después del corte, con su consulta y su historia. */
function certificadoCertAprobacion(?Servidor $servidor, array $extra = []): CertificadoMedico
{
    static $n = 0;
    $n++;

    $medico = User::factory()->create();
    // Sin servidor, el paciente es una hija del primer servidor. Una historia
    // por cédula (uq_hc_cedula_paciente): varios certificados la comparten.
    $familiar = $servidor ? null : CargaFamiliar::firstOrCreate(['cedula' => '0803333334'], [
        'servidor_id' => Servidor::query()->orderBy('id')->value('id'),
        'nombres' => 'Camila', 'apellidos' => 'Recalde', 'parentesco' => TipoParentesco::HIJO,
        'fecha_nacimiento' => now()->subYears(12), 'estado' => true,
    ]);
    $historia = HistoriaClinica::firstOrCreate(
        ['cedula_paciente' => $servidor?->cedula ?? $familiar->cedula],
        [
            'numero_historia' => "HC-CERT-{$n}", 'tipo_paciente' => $servidor ? 'servidor' : 'familiar',
            'servidor_id' => $servidor?->id, 'carga_familiar_id' => $familiar?->id, 'estado' => true,
        ],
    );
    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id, 'medico_id' => $medico->id, 'especialidad' => 'medicina_general',
        'fecha_consulta' => '2026-10-12', 'hora_consulta' => '09:00:00',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Reservado',
    ]);

    $id = DB::table('certificados_medicos')->insertGetId(array_merge([
        'consulta_medica_id' => $consulta->id, 'servidor_id' => $servidor?->id, 'emitido_por' => $medico->id,
        'dias_reposo' => 3, 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-14',
        'observaciones' => 'Dato clínico que no debe salir', 'folio' => sprintf('CERT-2026-%05d', 97000 + $n),
        'tipo_paciente' => $servidor ? 'servidor' : 'beneficiario',
        'created_at' => '2026-10-12 09:30:00', 'updated_at' => '2026-10-12 09:30:00',
    ], $extra));

    return CertificadoMedico::findOrFail($id);
}

function registroCertAprobacion(): array
{
    return [
        'userid' => 798,
        'filas' => [
            ['id' => 84301, 'inicio' => '2026-10-12 08:00:00', 'fin' => '2026-10-12 17:00:00'],
            ['id' => 84302, 'inicio' => '2026-10-13 08:00:00', 'fin' => '2026-10-13 17:00:00'],
            ['id' => 84303, 'inicio' => '2026-10-14 08:00:00', 'fin' => '2026-10-14 17:00:00'],
        ],
        'omitidos' => [],
        'ya_registrado' => false,
    ];
}

function aprobarCertAprobacion(User $quien, CertificadoMedico $c, int $leaveId = 13)
{
    return test()->actingAs($quien, 'sanctum')
        ->postJson("/api/v1/asistencia/certificados-medicos/{$c->id}/aprobar-sirha7", ['leave_id' => $leaveId]);
}

function aprobarSinSirha7CertAprobacion(User $quien, CertificadoMedico $c, ?string $nota = 'Cargado a mano en Sirha7 por TH')
{
    return test()->actingAs($quien, 'sanctum')
        ->postJson("/api/v1/asistencia/certificados-medicos/{$c->id}/aprobar-sin-sirha7", ['nota' => $nota]);
}

// ── Aprobar y registrar ──────────────────────────────────────────────

test('Talento Humano aprueba: se registran los días del reposo, de jornada completa, con la referencia del folio', function () {
    $c = certificadoCertAprobacion($this->servidor);

    $this->sirha7->shouldReceive('registrar')
        ->once()
        ->with('0802704171', 13, '2026-10-12', '2026-10-14', null, null, "SGTH {$c->folio}")
        ->andReturn(registroCertAprobacion());

    $res = aprobarCertAprobacion($this->uath, $c)->assertOk();

    $c->refresh();
    expect($c->aprobado_por)->toBe($this->uath->id)
        ->and($c->aprobado_en)->not->toBeNull()
        ->and($c->registro_sirha7)->toBe(CertificadoMedico::REGISTRO_SGTH)
        ->and($c->sirha7_leave_nombre)->toBe('DISPENSARIO MÉDICO GADPE')
        ->and($c->sirha7_userid)->toBe(798)
        ->and($c->sirha7_referencia)->toBe("SGTH {$c->folio}")
        ->and($c->filasSirha7()->pluck('sirha7_id')->sort()->values()->all())->toBe([84301, 84302, 84303])
        ->and($res->json('datos'))->not->toHaveKeys(['observaciones', 'diagnostico_cie10_id', 'motivo_anulacion'])
        ->and($res->json('datos.pendiente'))->toBeFalse();
});

test('aprueban también asistente-uath y Trabajo Social', function (string $quien) {
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldReceive('registrar')->once()->andReturn(registroCertAprobacion());

    aprobarCertAprobacion($this->{$quien}, $c)->assertOk();
})->with(['asistente', 'ts']);

test('Recepción no ve la lista ni aprueba', function () {
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldNotReceive('registrar');

    $this->actingAs($this->recepcion, 'sanctum')->getJson('/api/v1/asistencia/certificados-medicos')->assertForbidden();
    aprobarCertAprobacion($this->recepcion, $c)->assertForbidden();
    aprobarSinSirha7CertAprobacion($this->recepcion, $c)->assertForbidden();
});

test('nadie aprueba su propio certificado', function () {
    $propio = usuarioCertAprobacion('admin-uath', $this->servidor);
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarCertAprobacion($propio, $c)->assertStatus(422);
    aprobarSinSirha7CertAprobacion($propio, $c)->assertStatus(422);
    expect($c->fresh()->aprobado_en)->toBeNull();
});

test('no se aprueba dos veces, ni uno anulado, ni el de un familiar', function () {
    $aprobado = certificadoCertAprobacion($this->servidor, ['aprobado_en' => '2026-10-12 12:00:00', 'aprobado_por' => $this->uath->id]);
    $anulado = certificadoCertAprobacion($this->servidor, ['anulado_en' => '2026-10-12 12:00:00']);
    $familiar = certificadoCertAprobacion(null);
    $this->sirha7->shouldNotReceive('registrar');

    expect(aprobarCertAprobacion($this->uath, $aprobado)->assertStatus(422)->json('mensaje'))->toContain('ya se aprobó')
        ->and(aprobarCertAprobacion($this->uath, $anulado)->assertStatus(422)->json('mensaje'))->toContain('anulado')
        ->and(aprobarCertAprobacion($this->uath, $familiar)->assertStatus(422)->json('mensaje'))->toContain('familiar');
});

test('un tipo que no está en Sirha7 se rechaza sin escribir nada', function () {
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarCertAprobacion($this->uath, $c, 999)->assertStatus(422);
    expect($c->fresh()->aprobado_en)->toBeNull();
});

test('si Sirha7 lo rechaza, el certificado sigue pendiente y se dice por qué', function () {
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldReceive('registrar')->once()->andThrow(
        new ReglaNegocioException('La cédula 0802704171 no está en el biométrico.')
    );

    expect(aprobarCertAprobacion($this->uath, $c)->assertStatus(422)->json('mensaje'))->toContain('no está en el biométrico')
        ->and($c->fresh()->aprobado_en)->toBeNull();
});

test('si Sirha7 no responde, 503 y el certificado sigue pendiente', function () {
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldReceive('registrar')->once()->andThrow(
        new QueryException('sqlsrv', 'EXEC dbo.sp_SGTH_RegistrarPermiso', [], new PDOException('timeout'))
    );

    aprobarCertAprobacion($this->uath, $c)->assertStatus(503);
    expect($c->fresh()->aprobado_en)->toBeNull()
        ->and(CertificadoSirha7Fila::count())->toBe(0);
});

// ── Sin Sirha7 ───────────────────────────────────────────────────────

test('lo emitido antes del corte no se escribe en Sirha7: se aprueba sin Sirha7, con nota', function () {
    $c = certificadoCertAprobacion($this->servidor, ['created_at' => '2026-09-20 09:00:00']);
    $this->sirha7->shouldNotReceive('registrar');

    $previa = $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/certificados-medicos/{$c->id}/sirha7")->assertOk()->json('datos');
    expect($previa['pendiente'])->toBeTrue()
        ->and($previa['registrable_en_sirha7'])->toBeFalse()
        ->and($previa['motivo_sin_sirha7'])->toContain('01/10/2026');

    aprobarCertAprobacion($this->uath, $c)->assertStatus(422);
    aprobarSinSirha7CertAprobacion($this->uath, $c)->assertOk();

    $c->refresh();
    expect($c->registro_sirha7)->toBe(CertificadoMedico::REGISTRO_MANUAL)
        ->and($c->nota_aprobacion)->toBe('Cargado a mano en Sirha7 por TH')
        ->and($c->aprobado_por)->toBe($this->uath->id)
        ->and($c->sirha7_referencia)->toBeNull();
});

test('sin fecha de corte, solo se puede aprobar sin Sirha7', function () {
    config(['services.biometrico.aprobacion_permisos_desde' => null]);
    $c = certificadoCertAprobacion($this->servidor);
    $this->sirha7->shouldNotReceive('registrar');

    expect(aprobarCertAprobacion($this->uath, $c)->assertStatus(422)->json('mensaje'))->toContain('SIRHA7_APROBACION_DESDE');
    aprobarSinSirha7CertAprobacion($this->uath, $c)->assertOk();
});

test('aprobar sin Sirha7 exige la nota', function () {
    $c = certificadoCertAprobacion($this->servidor);

    aprobarSinSirha7CertAprobacion($this->uath, $c, null)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['nota']]);
    aprobarSinSirha7CertAprobacion($this->uath, $c, 'corta')->assertStatus(422);
});

// ── La lista y la previa ─────────────────────────────────────────────

test('la lista trae solo certificados de servidores, por estado, sin datos clínicos', function () {
    $pendiente = certificadoCertAprobacion($this->servidor);
    $aprobado = certificadoCertAprobacion($this->servidor, ['aprobado_en' => '2026-10-12 12:00:00', 'aprobado_por' => $this->uath->id]);
    $anulado = certificadoCertAprobacion($this->servidor, ['anulado_en' => '2026-10-12 12:00:00', 'motivo_anulacion' => 'Lumbalgia mal digitada']);
    certificadoCertAprobacion(null);

    $folios = fn (?string $estado) => collect(
        $this->actingAs($this->ts, 'sanctum')
            ->getJson('/api/v1/asistencia/certificados-medicos' . ($estado ? "?estado={$estado}" : ''))
            ->assertOk()->json('datos.data')
    )->pluck('folio')->sort()->values()->all();

    expect($folios(null))->toBe(collect([$pendiente, $aprobado, $anulado])->pluck('folio')->sort()->values()->all())
        ->and($folios('pendiente'))->toBe([$pendiente->folio])
        ->and($folios('aprobado'))->toBe([$aprobado->folio])
        ->and($folios('anulado'))->toBe([$anulado->folio]);

    $fila = $this->actingAs($this->ts, 'sanctum')->getJson('/api/v1/asistencia/certificados-medicos?estado=anulado')->json('datos.data.0');
    expect($fila)->not->toHaveKeys(['observaciones', 'diagnostico_cie10_id', 'motivo_anulacion', 'consulta_medica_id'])
        ->and($fila['servidor']['apellido'])->toBe('Recalde')
        ->and($fila['dias_reposo'])->toBe(3);
});

test('el filtro de fechas toma cualquier día del reposo', function () {
    $c = certificadoCertAprobacion($this->servidor);

    $buscar = fn (string $q) => $this->actingAs($this->uath, 'sanctum')
        ->getJson("/api/v1/asistencia/certificados-medicos?{$q}")->assertOk()->json('datos.data');

    expect($buscar('fecha_desde=2026-10-14&fecha_hasta=2026-10-20'))->toHaveCount(1)
        ->and($buscar('fecha_desde=2026-10-15'))->toHaveCount(0);

    $this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/certificados-medicos?fecha_desde=no-es-fecha')
        ->assertStatus(422);
});

test('la previa avisa del permiso de la consulta', function () {
    $consulta = PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-10-12',
        'hora_inicio' => '08:00', 'hora_fin' => '09:00', 'estado' => EstadoPermiso::PENDIENTE->value,
        'folio' => 'PER-2026-97002', 'vence_en' => '2026-10-15',
    ]);
    $c = certificadoCertAprobacion($this->servidor);

    $previa = $this->actingAs($this->ts, 'sanctum')
        ->getJson("/api/v1/asistencia/certificados-medicos/{$c->id}/sirha7")->assertOk()->json('datos');

    expect($previa['desde'])->toBe('2026-10-12')
        ->and($previa['hasta'])->toBe('2026-10-14')
        ->and($previa['jornada_completa'])->toBeTrue()
        ->and($previa['registrable_en_sirha7'])->toBeTrue()
        ->and(array_keys($previa['certificado']))->toBe(['folio', 'fecha_inicio', 'fecha_fin', 'dias_reposo', 'medico'])
        ->and(array_column($previa['cruces'], 'folio'))->toBe([$consulta->folio]);
});

// ── El reposo ocupa sus días desde que se emite ──────────────────────

test('sobre un reposo no se piden vacaciones ni otro permiso, aunque no esté aprobado', function () {
    $c = certificadoCertAprobacion($this->servidor, ['fecha_inicio' => '2026-11-16', 'fecha_fin' => '2026-11-18']);

    $permiso = fn (string $fecha) => app(\App\Services\Asistencia\PermisoService::class)->crear([
        'tipo' => TipoPermiso::OFICIAL->value, 'fecha' => $fecha, 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        'observacion' => 'Diligencia',
    ], $this->servidor->id);

    $this->travelTo(Carbon\Carbon::parse('2026-11-16 08:00'));

    expect(fn () => $permiso('2026-11-17'))->toThrow(ReglaNegocioException::class, $c->folio);
    expect(fn () => app(\App\Services\Asistencia\VacacionService::class)->solicitar([
        'fecha_inicio' => '2026-11-18', 'fecha_fin' => '2026-11-20',
    ], $this->servidor->id))->toThrow(ReglaNegocioException::class, $c->folio);

    // Anulado, deja los días libres.
    DB::table('certificados_medicos')->where('id', $c->id)->update(['anulado_en' => now()]);
    expect($permiso('2026-11-17')->folio)->not->toBeNull();
});

// ── La migración de los ya emitidos ──────────────────────────────────

test('la migración pasa al certificado la aprobación y las filas de su permiso, y borra el permiso', function () {
    $permiso = PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-10-12',
        'hora_inicio' => '00:00', 'hora_fin' => '23:59', 'estado' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
        'folio' => 'PER-2026-97010', 'vence_en' => '2026-10-14', 'confirmado_en' => '2026-10-12 09:30:00',
        'sirha7_aprobado_en' => '2026-10-12 10:00:00', 'sirha7_aprobado_por' => $this->ts->id, 'sirha7_leave_id' => 13,
        'sirha7_leave_nombre' => 'DISPENSARIO MÉDICO GADPE', 'sirha7_userid' => 798,
        'validado_ts_por' => $this->ts->id, 'validado_ts_en' => '2026-10-12 10:00:00',
    ]);
    foreach ([84117, 84118] as $id) {
        \App\Models\Asistencia\PermisoSirha7Fila::create([
            'permiso_servidor_id' => $permiso->id, 'sirha7_id' => $id, 'inicio' => '2026-10-12 08:00', 'fin' => '2026-10-12 17:00',
        ]);
    }
    $registrado = certificadoCertAprobacion($this->servidor, ['permiso_servidor_id' => $permiso->id, 'folio' => 'PER-2026-97010']);

    $validadoSinSirha7 = PermisoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo' => TipoPermiso::ENFERMEDAD->value, 'fecha' => '2026-09-20',
        'hora_inicio' => '00:00', 'hora_fin' => '23:59', 'estado' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
        'folio' => 'PER-2026-97011', 'vence_en' => '2026-09-22', 'confirmado_en' => '2026-09-20 09:30:00',
        'validado_ts_por' => $this->ts->id, 'validado_ts_en' => '2026-09-21 10:00:00',
    ]);
    $manual = certificadoCertAprobacion($this->servidor, [
        'permiso_servidor_id' => $validadoSinSirha7->id, 'folio' => 'PER-2026-97011',
        'fecha_inicio' => '2026-09-20', 'fecha_fin' => '2026-09-21', 'dias_reposo' => 2,
    ]);

    $migracion = require database_path('migrations/2026_10_08_130000_pasar_reposos_del_permiso_al_certificado.php');
    $migracion->up();

    $registrado->refresh();
    expect($registrado->registro_sirha7)->toBe(CertificadoMedico::REGISTRO_SGTH)
        ->and($registrado->aprobado_por)->toBe($this->ts->id)
        ->and($registrado->sirha7_referencia)->toBe('SGTH PER-2026-97010')
        ->and($registrado->sirha7_userid)->toBe(798)
        ->and($registrado->filasSirha7()->pluck('sirha7_id')->sort()->values()->all())->toBe([84117, 84118])
        ->and(PermisoServidor::find($permiso->id))->toBeNull()
        ->and(PermisoServidor::withTrashed()->find($permiso->id))->not->toBeNull();

    $manual->refresh();
    expect($manual->registro_sirha7)->toBe(CertificadoMedico::REGISTRO_MANUAL)
        ->and($manual->nota_aprobacion)->toContain('PER-2026-97011')
        ->and($manual->aprobado_por)->toBe($this->ts->id);

    // Y se puede deshacer.
    $migracion->down();

    expect(PermisoServidor::find($permiso->id))->not->toBeNull()
        ->and($permiso->fresh()->filasSirha7()->count())->toBe(2)
        ->and($registrado->fresh()->aprobado_en)->toBeNull()
        ->and($registrado->fresh()->filasSirha7()->count())->toBe(0)
        ->and($manual->fresh()->aprobado_en)->toBeNull();
});

test('al emitir, el certificado guarda su servidor; el de un familiar, ninguno', function () {
    $servicio = app(\App\Services\Dispensario\CertificadoMedicoService::class);
    $base = certificadoCertAprobacion($this->servidor);
    $deFamiliar = certificadoCertAprobacion(null);

    $emitido = $servicio->emitir([
        'consulta_medica_id' => $base->consulta_medica_id, 'dias_reposo' => 2,
        'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-21',
    ], $base->emitido_por);
    $emitidoFamiliar = $servicio->emitir([
        'consulta_medica_id' => $deFamiliar->consulta_medica_id, 'dias_reposo' => 1,
        'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-20',
    ], $deFamiliar->emitido_por);

    expect($emitido->servidor_id)->toBe($this->servidor->id)
        ->and($emitido->estaPendienteDeAprobacion())->toBeTrue()
        ->and($emitidoFamiliar->servidor_id)->toBeNull()
        ->and($emitidoFamiliar->estaPendienteDeAprobacion())->toBeFalse();
});

// ── Retirar ──────────────────────────────────────────────────────────

test('retirar quita de Sirha7 exactamente las filas que escribió el SGTH, con la referencia guardada', function () {
    $c = certificadoCertAprobacion($this->servidor, [
        'aprobado_en' => '2026-10-12 12:00:00', 'aprobado_por' => $this->uath->id,
        'registro_sirha7' => CertificadoMedico::REGISTRO_SGTH, 'sirha7_userid' => 798,
        'sirha7_referencia' => 'SGTH PER-2026-00005', 'sirha7_leave_id' => 13,
    ]);
    foreach ([84117, 84118] as $id) {
        CertificadoSirha7Fila::create(['certificado_medico_id' => $c->id, 'sirha7_id' => $id, 'inicio' => now(), 'fin' => now()]);
    }

    $this->sirha7->shouldReceive('retirar')->once()->with('SGTH PER-2026-00005', 798, 2);

    app(AprobacionCertificadoSirha7Service::class)->retirar($c);

    $c->refresh();
    expect($c->filasSirha7()->count())->toBe(0)
        ->and($c->registro_sirha7)->toBeNull()
        ->and($c->sirha7_referencia)->toBeNull()
        ->and($c->aprobado_en)->not->toBeNull();
});

test('retirar no toca Sirha7 si el certificado se aprobó a mano', function () {
    $c = certificadoCertAprobacion($this->servidor, [
        'aprobado_en' => '2026-10-12 12:00:00', 'aprobado_por' => $this->uath->id,
        'registro_sirha7' => CertificadoMedico::REGISTRO_MANUAL, 'nota_aprobacion' => 'Cargado a mano',
    ]);
    $this->sirha7->shouldNotReceive('retirar');

    app(AprobacionCertificadoSirha7Service::class)->retirar($c);

    expect($c->fresh()->registro_sirha7)->toBe(CertificadoMedico::REGISTRO_MANUAL);
});
