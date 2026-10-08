<?php

/*
| Deshacer un permiso ya aprobado en Sirha7 lo retira de allí (decisión del
| 2026-10-07): revertir la confirmación y anular el certificado médico.
|
| Sirha7 va primero. Si se niega o no responde, no se deshace nada en el SGTH:
| un permiso revertido aquí y todavía justificando la ausencia en el
| biométrico es justo lo que no debe pasar.
|
| Sirha7 se reemplaza con un doble (el CI no tiene SQL Server). El
| procedimiento de retiro se probó contra Sirha7 en el PR #378.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\PermisoSirha7Fila;
use App\Models\Dispensario\CertificadoMedico;
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
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    PermisoServidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    config(['services.biometrico.aprobacion_permisos_desde' => '2026-10-01']);

    $unidad = unidadDePrueba(['nombre' => 'Dirección Retiro Sirha7']);
    $this->servidor = Servidor::create([
        'cedula' => '0802704171', 'nombre' => 'Cristhian', 'apellido' => 'Recalde',
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->uath = User::create([
        'email' => 'retiro-sirha7@example.com', 'usuario_ti' => 'retiro_s7',
        'password' => bcrypt('123456'), 'primer_login' => false,
    ]);
    $this->uath->assignRole('admin-uath');

    $this->sirha7 = $this->mock(Sirha7PermisoService::class);
});

/** Un permiso ya aprobado en Sirha7, con `$filas` filas registradas allí. */
function permisoRetiroSirha7(Servidor $s, TipoPermiso $tipo, int $filas = 1, array $extra = []): PermisoServidor
{
    static $n = 0;
    $n++;

    $p = PermisoServidor::create(array_merge([
        'servidor_id' => $s->id, 'unidad_administrativa_id' => $s->unidad_administrativa_id,
        'tipo' => $tipo->value, 'fecha' => '2026-10-12', 'hora_inicio' => '08:00', 'hora_fin' => '10:00',
        'observacion' => 'Diligencia', 'estado' => EstadoPermiso::ACTIVO->value,
        'folio' => sprintf('PER-2026-%05d', 95000 + $n), 'vence_en' => '2026-10-15',
        'confirmado_en' => '2026-10-12 11:00:00',
        'sirha7_leave_id' => 19, 'sirha7_leave_nombre' => 'OFICIAL', 'sirha7_userid' => 798,
        'sirha7_aprobado_en' => '2026-10-12 12:00:00',
    ], $extra));

    for ($i = 0; $i < $filas; $i++) {
        PermisoSirha7Fila::create([
            'permiso_servidor_id' => $p->id, 'sirha7_id' => 84200 + $n * 10 + $i,
            'inicio' => '2026-10-12 08:00:00', 'fin' => '2026-10-12 10:00:00',
        ]);
    }

    return $p;
}

function revertirRetiroSirha7(User $quien, PermisoServidor $p)
{
    return test()->actingAs($quien, 'sanctum')
        ->postJson("/api/v1/asistencia/permisos/{$p->id}/revertir-confirmacion", ['motivo' => 'Se confirmó el folio equivocado.']);
}

function seguiaAprobadoSirha7(PermisoServidor $p, int $filas): void
{
    $p->refresh();
    expect($p->estado)->toBe(EstadoPermiso::ACTIVO)
        ->and($p->sirha7_aprobado_en)->not->toBeNull()
        ->and($p->filasSirha7()->count())->toBe($filas);
}

// ── Revertir la confirmación ─────────────────────────────────────────

test('revertir un permiso aprobado lo retira de Sirha7 con el número exacto de filas', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('retirar')->once()->with("SGTH {$p->folio}", 798, 1)->andReturn(1);

    revertirRetiroSirha7($this->uath, $p)->assertOk();

    $p->refresh();
    expect($p->estado)->toBe(EstadoPermiso::PENDIENTE)
        ->and($p->sirha7_aprobado_en)->toBeNull()
        ->and($p->sirha7_leave_id)->toBeNull()
        ->and($p->sirha7_userid)->toBeNull()
        ->and($p->filasSirha7()->count())->toBe(0);
});

test('si Sirha7 se niega a retirarlo, la reversión no ocurre', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('retirar')->andThrow(new ReglaNegocioException(
        "Se esperaban 1 filas con la referencia SGTH {$p->folio} y hay 2: no se borra ninguna. Revísese a mano en Sirha7."
    ));

    revertirRetiroSirha7($this->uath, $p)->assertStatus(422)
        ->assertJsonPath('mensaje', "Se esperaban 1 filas con la referencia SGTH {$p->folio} y hay 2: no se borra ninguna. Revísese a mano en Sirha7.");

    seguiaAprobadoSirha7($p, 1);
});

test('si Sirha7 no responde, 503 y la reversión no ocurre', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('retirar')->andThrow(
        new QueryException('sqlsrv', 'EXEC dbo.sp_SGTH_RetirarPermiso', [], new PDOException('timeout'))
    );

    revertirRetiroSirha7($this->uath, $p)->assertStatus(503);

    seguiaAprobadoSirha7($p, 1);
});

test('revertir un permiso que no se aprobó en Sirha7 no llama al biométrico', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::OFICIAL, 0, [
        'sirha7_leave_id' => null, 'sirha7_leave_nombre' => null, 'sirha7_userid' => null, 'sirha7_aprobado_en' => null,
    ]);
    $this->sirha7->shouldNotReceive('retirar');

    revertirRetiroSirha7($this->uath, $p)->assertOk();

    expect($p->fresh()->estado)->toBe(EstadoPermiso::PENDIENTE);
});

test('un permiso revertido se puede volver a aprobar después', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::OFICIAL);
    $this->sirha7->shouldReceive('retirar')->andReturn(1);

    revertirRetiroSirha7($this->uath, $p)->assertOk();
    $p->refresh()->forceFill(['estado' => EstadoPermiso::ACTIVO->value, 'confirmado_en' => '2026-10-12 15:00:00'])->save();

    expect($p->fresh()->pendiente_sirha7)->toBeTrue();
});

// ── Anular el certificado médico ─────────────────────────────────────

test('anular el certificado retira de Sirha7 los días de reposo aprobados', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::ENFERMEDAD, 3, [
        'estado' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value, 'hora_inicio' => '00:00', 'hora_fin' => '23:59',
    ]);
    [$certificado, $medico] = certificadoRetiroSirha7($p);
    $this->sirha7->shouldReceive('retirar')->once()->with("SGTH {$p->folio}", 798, 3)->andReturn(3);

    anularCertificadoRetiroSirha7($medico, $certificado)->assertOk();

    $p->refresh();
    expect($p->estado)->toBe(EstadoPermiso::ANULADO)
        ->and($p->sirha7_aprobado_en)->toBeNull()
        ->and($p->filasSirha7()->count())->toBe(0)
        ->and($certificado->fresh()->anulado_en)->not->toBeNull();
});

test('si Sirha7 se niega, el certificado no se anula', function () {
    $p = permisoRetiroSirha7($this->servidor, TipoPermiso::ENFERMEDAD, 3, [
        'estado' => EstadoPermiso::VALIDADO_TRABAJO_SOCIAL->value,
    ]);
    [$certificado, $medico] = certificadoRetiroSirha7($p);
    $this->sirha7->shouldReceive('retirar')->andThrow(new ReglaNegocioException('Hay una solicitud del módulo web idéntica.'));

    anularCertificadoRetiroSirha7($medico, $certificado)->assertStatus(422);

    expect($certificado->fresh()->anulado_en)->toBeNull()
        ->and($p->fresh()->estado)->toBe(EstadoPermiso::VALIDADO_TRABAJO_SOCIAL)
        ->and($p->fresh()->filasSirha7()->count())->toBe(3);
});

/** @return array{0: CertificadoMedico, 1: User} */
function certificadoRetiroSirha7(PermisoServidor $p): array
{
    ConsultaMedica::unguard();
    $medico = User::factory()->create();
    $medico->assignRole(Role::findByName('medico', 'sanctum'));

    $historia = HistoriaClinica::create([
        'numero_historia' => '0802704171', 'cedula_paciente' => '0802704171',
        'tipo_paciente' => 'servidor', 'servidor_id' => $p->servidor_id, 'estado' => true,
    ]);
    $consulta = ConsultaMedica::create([
        'historia_clinica_id' => $historia->id, 'medico_id' => $medico->id, 'especialidad' => 'medicina_general',
        'fecha_consulta' => '2026-10-12', 'hora_consulta' => '09:00:00',
        'motivo_consulta' => 'Control', 'diagnostico_detallado' => 'Reservado',
    ]);
    $id = DB::table('certificados_medicos')->insertGetId([
        'consulta_medica_id' => $consulta->id, 'emitido_por' => $medico->id, 'dias_reposo' => 3,
        'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-14', 'permiso_servidor_id' => $p->id,
        'folio' => 'CM-2026-95001', 'tipo_paciente' => 'servidor', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [CertificadoMedico::findOrFail($id), $medico];
}

function anularCertificadoRetiroSirha7(User $medico, CertificadoMedico $c)
{
    return test()->actingAs($medico, 'sanctum')
        ->patchJson("/api/v1/dispensario/certificados-medicos/{$c->id}/anular", ['motivo_anulacion' => 'Días de reposo mal digitados']);
}
