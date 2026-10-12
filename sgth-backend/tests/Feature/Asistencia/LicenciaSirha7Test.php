<?php

/*
| Fase 5.1: aprobar una licencia con remuneración la registra en Sirha7, como
| los certificados médicos (2026-10-08). Quien aprueba elige el tipo; las de
| salud y la calamidad las aprueba Trabajo Social; lo que el SGTH no puede
| escribir se aprueba sin Sirha7; anularla retira lo escrito.
|
| El procedimiento no admite más de 31 días: la licencia larga va en tramos
| parejos, cada uno con su referencia.
|
| Sirha7 se reemplaza con un doble: el CI no tiene SQL Server.
*/

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\LicenciaSirha7Fila;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\Sirha7PermisoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    Storage::fake('local');
    config(['services.biometrico.aprobacion_permisos_desde' => '2026-10-01']);
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $unidad = unidadDePrueba(['nombre' => 'Dirección Licencias Sirha7']);
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    ContratoServidor::create([
        'servidor_id' => $this->servidor->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $this->servidor->puesto_id,
        'fecha_inicio' => '2020-01-02', 'estado' => 'vigente',
    ]);

    $this->uath = usuarioLicSirha7('admin-uath');
    $this->asistente = usuarioLicSirha7('asistente-uath');
    $this->ts = usuarioLicSirha7('trabajo-social');

    $this->sirha7 = $this->mock(Sirha7PermisoService::class);
    $this->sirha7->shouldReceive('tipos')->andReturn([
        ['id' => 13, 'nombre' => 'MATERNIDAD'],
        ['id' => 14, 'nombre' => 'ENFERMEDAD'],
    ])->byDefault();

    $this->licencia = function (string $causa, string $desde, string $hasta, ?string $alcance = null): Licencia {
        $this->actingAs($this->asistente, 'sanctum');
        $id = $this->post('/api/v1/asistencia/licencias', [
            'servidor_id' => $this->servidor->id, 'causa' => $causa, 'alcance' => $alcance,
            'fecha_inicio' => $desde, 'fecha_fin' => $hasta,
            'archivo' => UploadedFile::fake()->create('respaldo.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('datos.id');

        return Licencia::findOrFail($id);
    };
});

function usuarioLicSirha7(string $rol, ?Servidor $servidor = null): User
{
    $u = User::create([
        'email' => uniqid('ls7').'@example.com', 'usuario_ti' => uniqid('ls'),
        'password' => bcrypt('123456'), 'primer_login' => false, 'servidor_id' => $servidor?->id,
    ]);
    $u->assignRole($rol);

    return $u;
}

/** Lo que devuelve el procedimiento: una fila por día, desde el ID que se le dé. */
function registroLicSirha7(string $desde, string $hasta, int $primerId): array
{
    $filas = [];
    $dia = Carbon::parse($desde);
    $id = $primerId;

    while ($dia->lte(Carbon::parse($hasta))) {
        $filas[] = ['id' => $id++, 'inicio' => $dia->format('Y-m-d').' 08:00:00', 'fin' => $dia->format('Y-m-d').' 17:00:00'];
        $dia->addDay();
    }

    return ['userid' => 798, 'filas' => $filas, 'omitidos' => [], 'ya_registrado' => false];
}

function aprobarLicSirha7(User $quien, Licencia $licencia, int $leaveId = 13)
{
    return test()->actingAs($quien, 'sanctum')
        ->postJson("/api/v1/asistencia/licencias/{$licencia->id}/aprobar-sirha7", ['leave_id' => $leaveId]);
}

// ── Aprobar y registrar ─────────────────────────────────────────

test('aprobarla la registra en Sirha7 con el tipo elegido y la referencia del folio', function () {
    $licencia = ($this->licencia)('matrimonio', '2026-10-19', '2026-10-21');

    $this->sirha7->shouldReceive('registrar')->once()
        ->with('0802704171', 13, '2026-10-19', '2026-10-21', null, null, 'SGTH LIC-2026-00001')
        ->andReturn(registroLicSirha7('2026-10-19', '2026-10-21', 90001));

    aprobarLicSirha7($this->asistente, $licencia)
        ->assertOk()
        ->assertJsonPath('datos.estado', 'aprobada')
        ->assertJsonPath('datos.registro_sirha7', 'sgth')
        ->assertJsonPath('datos.sirha7_leave_nombre', 'MATERNIDAD');

    expect(LicenciaSirha7Fila::where('licencia_id', $licencia->id)->pluck('referencia')->unique()->all())
        ->toBe(['SGTH LIC-2026-00001'])
        ->and($licencia->fresh()->sirha7_userid)->toBe(798);
});

test('la de más de 31 días va en tramos parejos, cada uno con su referencia', function () {
    // 12 semanas: tres tramos de 28 días.
    $licencia = ($this->licencia)('maternidad', '2026-10-19', '2027-01-10');

    expect(app(\App\Services\Asistencia\AprobacionLicenciaSirha7Service::class)->tramos($licencia))->toBe([
        ['desde' => '2026-10-19', 'hasta' => '2026-11-15', 'referencia' => 'SGTH LIC-2026-00001-1'],
        ['desde' => '2026-11-16', 'hasta' => '2026-12-13', 'referencia' => 'SGTH LIC-2026-00001-2'],
        ['desde' => '2026-12-14', 'hasta' => '2027-01-10', 'referencia' => 'SGTH LIC-2026-00001-3'],
    ]);

    $id = 91000;
    $this->sirha7->shouldReceive('registrar')->times(3)
        ->andReturnUsing(function ($cedula, $leave, $desde, $hasta) use (&$id) {
            $registro = registroLicSirha7($desde, $hasta, $id);
            $id += 100;

            return $registro;
        });

    aprobarLicSirha7($this->asistente, $licencia)->assertOk();

    expect(LicenciaSirha7Fila::where('licencia_id', $licencia->id)->count())->toBe(84)
        ->and(LicenciaSirha7Fila::where('referencia', 'SGTH LIC-2026-00001-2')->count())->toBe(28);
});

test('si un tramo falla, se retiran los ya escritos y la licencia sigue pendiente', function () {
    $licencia = ($this->licencia)('maternidad', '2026-10-19', '2027-01-10');

    $this->sirha7->shouldReceive('registrar')->once()->ordered()
        ->andReturn(registroLicSirha7('2026-10-19', '2026-11-15', 92000));
    $this->sirha7->shouldReceive('registrar')->once()->ordered()
        ->andThrow(new ReglaNegocioException('Sirha7 rechazó el tramo.'));
    $this->sirha7->shouldReceive('retirar')->once()->with('SGTH LIC-2026-00001-1', 798, 28)->andReturn(28);

    aprobarLicSirha7($this->asistente, $licencia)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Sirha7 rechazó el tramo.');

    expect($licencia->fresh()->estado())->toBe('pendiente')
        ->and(LicenciaSirha7Fila::count())->toBe(0);
});

test('anularla retira de Sirha7 cada tramo', function () {
    $licencia = ($this->licencia)('maternidad', '2026-10-19', '2027-01-10');
    $id = 93000;
    $this->sirha7->shouldReceive('registrar')->times(3)
        ->andReturnUsing(function ($cedula, $leave, $desde, $hasta) use (&$id) {
            $registro = registroLicSirha7($desde, $hasta, $id);
            $id += 100;

            return $registro;
        });
    aprobarLicSirha7($this->asistente, $licencia)->assertOk();

    foreach ([1, 2, 3] as $tramo) {
        $this->sirha7->shouldReceive('retirar')->once()->with("SGTH LIC-2026-00001-{$tramo}", 798, 28)->andReturn(28);
    }

    $this->actingAs($this->asistente, 'sanctum')
        ->postJson("/api/v1/asistencia/licencias/{$licencia->id}/anular", ['motivo' => 'Fechas mal registradas.'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'anulada');

    expect(LicenciaSirha7Fila::count())->toBe(0)
        ->and($licencia->fresh()->registro_sirha7)->toBeNull();
});

test('sin la fecha de corte solo se aprueba sin Sirha7, con su nota', function () {
    config(['services.biometrico.aprobacion_permisos_desde' => null]);
    $licencia = ($this->licencia)('matrimonio', '2026-10-19', '2026-10-21');

    $this->getJson("/api/v1/asistencia/licencias/{$licencia->id}/sirha7")
        ->assertOk()
        ->assertJsonPath('datos.registrable_en_sirha7', false);

    aprobarLicSirha7($this->asistente, $licencia)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'SIRHA7_APROBACION_DESDE'));

    $this->postJson("/api/v1/asistencia/licencias/{$licencia->id}/aprobar-sin-sirha7", ['nota' => 'Cargada a mano en Sirha7 el 12/10.'])
        ->assertOk()
        ->assertJsonPath('datos.registro_sirha7', 'manual')
        ->assertJsonPath('datos.nota_aprobacion', 'Cargada a mano en Sirha7 el 12/10.');
});

test('las de salud las aprueba Trabajo Social; las demás, Talento Humano; nadie la propia', function () {
    $enfermedad = ($this->licencia)('enfermedad', '2026-10-19', '2026-10-30');
    $matrimonio = ($this->licencia)('matrimonio', '2026-11-02', '2026-11-04');

    aprobarLicSirha7($this->asistente, $enfermedad)->assertForbidden();
    aprobarLicSirha7($this->ts, $matrimonio)->assertForbidden();

    $this->sirha7->shouldReceive('registrar')->once()->andReturn(registroLicSirha7('2026-10-19', '2026-10-30', 94000));
    aprobarLicSirha7($this->ts, $enfermedad, 14)->assertOk();

    $propio = usuarioLicSirha7('asistente-uath', $this->servidor);
    aprobarLicSirha7($propio, $matrimonio)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su propia licencia'));
});

test('si Sirha7 no responde, 503 y la licencia no cambia', function () {
    $licencia = ($this->licencia)('matrimonio', '2026-10-19', '2026-10-21');
    $this->sirha7->shouldReceive('registrar')->once()->andThrow(
        new QueryException('sqlsrv', 'EXEC dbo.sp_SGTH_RegistrarPermiso', [], new PDOException('timeout'))
    );

    aprobarLicSirha7($this->asistente, $licencia)->assertStatus(503);

    expect($licencia->fresh()->estado())->toBe('pendiente')
        ->and(LicenciaSirha7Fila::count())->toBe(0);
});
