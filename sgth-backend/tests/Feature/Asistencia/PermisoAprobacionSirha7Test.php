<?php

/*
| Aprobar un permiso registrándolo en Sirha7 (decisiones de TH, 2026-10-07/08).
|
| - Paso después de Recepción: solo permisos activos, confirmados desde la
|   fecha de corte y sin aprobar.
| - Personal y oficial, TH (`aprobar-permiso-sirha7`); enfermedad y
|   calamidad, Trabajo Social, que al aprobarlas las valida.
| - El tipo de Sirha7 lo elige quien aprueba.
| - Los reposos del dispensario se aprueban como certificados
|   (CertificadoAprobacionSirha7Test).
|
| Sirha7 se reemplaza con un doble: el CI no tiene SQL Server. Los
| procedimientos se probaron contra Sirha7 en el PR #378.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\Sirha7PermisoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    config(['services.biometrico.aprobacion_permisos_desde' => '2026-10-01']);

    $unidad = unidadDePrueba(['nombre' => 'Dirección Sirha7']);
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->uath = usuarioAprobacionSirha7('admin-uath');
    $this->asistente = usuarioAprobacionSirha7('asistente-uath');
    $this->ts = usuarioAprobacionSirha7('trabajo-social');
    $this->recepcion = usuarioAprobacionSirha7('recepcion');

    $this->sirha7 = $this->mock(Sirha7PermisoService::class);
    $this->sirha7->shouldReceive('tipos')->andReturn([
        ['id' => 14, 'nombre' => 'ENFERMEDAD'],
        ['id' => 19, 'nombre' => 'OFICIAL'],
    ])->byDefault();
});

function usuarioAprobacionSirha7(string $rol, ?Servidor $servidor = null): User
{
    $u = User::create([
        'email' => uniqid('sirha7').'@example.com', 'usuario_ti' => uniqid('s7'),
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $u->assignRole($rol);

    return $u;
}

function permisoAprobacionSirha7(Servidor $s, TipoPermiso $tipo, array $extra = []): PermisoServidor
{
    static $n = 0;
    $n++;

    return PermisoServidor::create(array_merge([
        'servidor_id' => $s->id, 'unidad_administrativa_id' => $s->unidad_administrativa_id,
        'tipo' => $tipo->value, 'fecha' => '2026-10-12', 'hora_inicio' => '08:00', 'hora_fin' => '10:00',
        'observacion' => 'Diligencia', 'estado' => EstadoPermiso::ACTIVO->value,
        'folio' => sprintf('PER-2026-%05d', 90000 + $n), 'vence_en' => '2026-10-15',
        'confirmado_en' => '2026-10-12 11:00:00',
    ], $extra));
}

function registroSirha7(int $id = 84100): array
{
    return [
        'userid' => 798,
        'filas' => [['id' => $id, 'inicio' => '2026-10-12 08:00:00', 'fin' => '2026-10-12 10:00:00']],
        'omitidos' => [],
        'ya_registrado' => false,
    ];
}

function aprobarEnSirha7(User $quien, PermisoServidor $p, int $leaveId = 19)
{
    return test()->actingAs($quien, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$p->id}/aprobar-sirha7", ['leave_id' => $leaveId]);
}

// ── Quién aprueba ────────────────────────────────────────────────────

test('Talento Humano aprueba un oficial: se registra por horas con la referencia del folio', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);

    $this->sirha7->shouldReceive('registrar')
        ->once()
        ->with('0802704171', 19, '2026-10-12', '2026-10-12', '08:00', '10:00', "SGTH {$p->folio}")
        ->andReturn(registroSirha7());

    aprobarEnSirha7($this->uath, $p)->assertOk();

    $p->refresh();
    expect($p->sirha7_leave_id)->toBe(19)
        ->and($p->sirha7_leave_nombre)->toBe('OFICIAL')
        ->and($p->sirha7_userid)->toBe(798)
        ->and($p->sirha7_aprobado_por)->toBe($this->uath->id)
        ->and($p->sirha7_aprobado_en)->not->toBeNull()
        ->and($p->estado)->toBe(EstadoPermiso::ACTIVO)
        ->and($p->filasSirha7()->pluck('sirha7_id')->all())->toBe([84100])
        ->and($p->pendiente_sirha7)->toBeFalse();
});

test('el asistente de TH también aprueba personal y oficial', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::PERSONAL);
    $this->sirha7->shouldReceive('registrar')->once()->andReturn(registroSirha7());

    aprobarEnSirha7($this->asistente, $p)->assertOk();
});

test('Trabajo Social aprueba la enfermedad y en el mismo paso la valida', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::ENFERMEDAD);
    $this->sirha7->shouldReceive('registrar')->once()->andReturn(registroSirha7());

    aprobarEnSirha7($this->ts, $p, 14)->assertOk();

    $p->refresh();
    expect($p->estado)->toBe(EstadoPermiso::VALIDADO_TRABAJO_SOCIAL)
        ->and($p->validado_ts_por)->toBe($this->ts->id)
        ->and($p->sirha7_leave_nombre)->toBe('ENFERMEDAD');
});

test('cada uno aprueba solo lo suyo', function () {
    $oficial = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $enfermedad = permisoAprobacionSirha7($this->servidor, TipoPermiso::ENFERMEDAD);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarEnSirha7($this->ts, $oficial)->assertForbidden();
    aprobarEnSirha7($this->asistente, $enfermedad, 14)->assertForbidden();
    aprobarEnSirha7($this->recepcion, $oficial)->assertForbidden();
    aprobarEnSirha7(usuarioAprobacionSirha7('servidor'), $oficial)->assertForbidden();
});

test('nadie aprueba su propio permiso', function () {
    $propio = usuarioAprobacionSirha7('asistente-uath', $this->servidor);
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarEnSirha7($propio, $p)->assertStatus(422)
        ->assertJsonPath('mensaje', 'Nadie aprueba su propio permiso: lo debe aprobar otra persona.');
});

// ── Cuándo ───────────────────────────────────────────────────────────

test('no se aprueba lo que no está en condiciones', function (array $extra, ?string $corte, string $mensaje) {
    config(['services.biometrico.aprobacion_permisos_desde' => $corte]);
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL, $extra);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarEnSirha7($this->uath, $p)->assertStatus(422);
    expect(aprobarEnSirha7($this->uath, $p)->json('mensaje'))->toContain($mensaje)
        ->and($p->fresh()->filasSirha7()->count())->toBe(0)
        ->and($p->fresh()->sirha7_aprobado_por)->toBeNull();
})->with([
    'sin fecha de corte'       => [[], null, 'no está habilitada'],
    'confirmado antes del corte' => [['confirmado_en' => '2026-09-30 17:00:00'], '2026-10-01', 'antes del 01/10/2026'],
    'todavía pendiente'        => [['estado' => 'pendiente', 'confirmado_en' => null], '2026-10-01', 'confirmado por Recepción'],
    'ya aprobado'              => [['sirha7_aprobado_en' => '2026-10-12 12:00:00'], '2026-10-01', 'ya se aprobó'],
]);

test('el tipo tiene que existir en Sirha7', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldNotReceive('registrar');

    aprobarEnSirha7($this->uath, $p, 999)->assertStatus(422)
        ->assertJsonPath('mensaje', 'El tipo de permiso elegido no existe en Sirha7.');
});

test('el tipo es obligatorio', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);

    $this->actingAs($this->uath, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$p->id}/aprobar-sirha7", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['leave_id']]);
});

// ── Lo que dice Sirha7 ───────────────────────────────────────────────

test('si Sirha7 rechaza el registro, el permiso no cambia', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('registrar')->andThrow(
        new ReglaNegocioException('No se registró el permiso del 12/10/2026: cruce con FERIADO de 08:00 a 17:00.')
    );

    aprobarEnSirha7($this->uath, $p)->assertStatus(422)
        ->assertJsonPath('mensaje', 'No se registró el permiso del 12/10/2026: cruce con FERIADO de 08:00 a 17:00.');

    expect($p->fresh()->sirha7_aprobado_en)->toBeNull()
        ->and($p->fresh()->filasSirha7()->count())->toBe(0);
});

test('si el biométrico no responde, 503 y el permiso no cambia', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('registrar')->andThrow(
        new QueryException('sqlsrv', 'EXEC dbo.sp_SGTH_RegistrarPermiso', [], new PDOException('timeout'))
    );

    aprobarEnSirha7($this->uath, $p)->assertStatus(503);

    expect($p->fresh()->sirha7_aprobado_en)->toBeNull();
});

test('un reintento que Sirha7 ya tenía registrado se guarda igual, sin duplicar filas', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('registrar')->andReturn(['ya_registrado' => true] + registroSirha7());

    aprobarEnSirha7($this->uath, $p)->assertOk();

    expect($p->fresh()->filasSirha7()->count())->toBe(1);
});

test('los días omitidos quedan anotados', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('registrar')->andReturn(
        ['omitidos' => [['dia' => '2026-10-13', 'motivo' => 'cruce con FERIADO de 08:00 a 17:00']]] + registroSirha7()
    );

    aprobarEnSirha7($this->uath, $p)->assertOk();

    expect($p->fresh()->sirha7_dias_omitidos)->toBe([['dia' => '2026-10-13', 'motivo' => 'cruce con FERIADO de 08:00 a 17:00']]);
});

// ── El permiso del dispensario ───────────────────────────────────────

// ── Lo demás del módulo ──────────────────────────────────────────────

test('después del corte, la enfermedad ya no se valida suelta', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::ENFERMEDAD);

    $this->actingAs($this->ts, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$p->id}/validar-ts")
        ->assertStatus(422);

    expect($p->fresh()->estado)->toBe(EstadoPermiso::ACTIVO);
});

test('antes del corte, la enfermedad se sigue validando como siempre', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::ENFERMEDAD, ['confirmado_en' => '2026-09-20 10:00:00']);

    $this->actingAs($this->ts, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$p->id}/validar-ts")
        ->assertOk();
});

test('el listado dice qué permisos están pendientes de Sirha7', function () {
    $p = permisoAprobacionSirha7($this->servidor, TipoPermiso::OFICIAL);

    $fila = collect($this->actingAs($this->uath, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos?estado=activo')
        ->assertOk()
        ->json('datos.data'))->firstWhere('id', $p->id);

    expect($fila['pendiente_sirha7'])->toBeTrue();
});

test('los tipos los ven quienes aprueban, y nadie más', function () {
    $this->actingAs($this->uath, 'sanctum')->getJson('/api/v1/asistencia/permisos/sirha7/tipos')
        ->assertOk()->assertJsonPath('datos.1.nombre', 'OFICIAL');
    $this->actingAs($this->ts, 'sanctum')->getJson('/api/v1/asistencia/permisos/sirha7/tipos')->assertOk();
    $this->actingAs($this->recepcion, 'sanctum')->getJson('/api/v1/asistencia/permisos/sirha7/tipos')->assertForbidden();
});
