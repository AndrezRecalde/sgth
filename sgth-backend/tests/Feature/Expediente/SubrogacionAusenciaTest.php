<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\Licencia;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Asistencia\LicenciaService;
use App\Services\Asistencia\VacacionService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 5.2 del diseño de Acciones de Personal (9.5): la subrogación se enlaza
| con la ausencia del titular que la motiva —vacaciones, licencia, reposo o
| acción de personal—, cabe dentro de sus fechas y la sigue: si la ausencia se
| anula, se cancela; si un reintegro la acorta, termina la víspera.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    Vacacion::unguard();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'DF-AUS', 'nombre' => 'Dirección Financiera', 'nivel' => 2]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1, 'activo' => true, 'es_jefe' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Director/a Financiero'])->id,
        'grupo_ocupacional_id' => grupoNjsDePrueba()->id,
    ]);

    $this->contador = 0;
    $this->servidor = function (bool $titular = false): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (6200000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Ausencia'.$this->contador, 'regimen_laboral' => 'losep',
        ]);
        if ($titular) {
            ContratoServidor::create([
                'servidor_id' => $servidor->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
                'unidad_administrativa_id' => $this->unidad->id, 'puesto_id' => $this->puesto->id,
                'fecha_inicio' => '2018-01-02', 'estado' => 'vigente',
            ]);
        }

        return $servidor->fresh();
    };

    $this->titular = ($this->servidor)(true);
    $this->service = app(SubrogacionService::class);

    $this->vacaciones = fn (string $estado = 'aprobada') => Vacacion::create([
        'servidor_id' => $this->titular->id, 'folio' => 'VAC-2026-0'.(++$this->contador), 'motivo' => 'vacaciones_anuales',
        'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-02', 'dias_solicitados' => 15,
        'tipo_dias' => 'calendario', 'estado' => $estado,
    ]);

    $this->comision = fn () => MovimientoPersonal::create([
        'servidor_id' => $this->titular->id,
        'tipo_movimiento' => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0'.(++$this->contador),
        'descripcion' => 'Comisión', 'fecha_efectiva' => '2026-10-15',
        'fecha_inicio' => '2026-10-15', 'fecha_fin' => '2026-12-15', 'institucion_destino' => 'MSP',
    ]);

    $this->subrogar = fn (array $ausencia, array $extra = []) => $this->service->registrar([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => ($this->servidor)()->id,
        'servidor_subrogado_id' => $this->titular->id, 'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $this->puesto->id, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-30',
        ...$ausencia,
        ...$extra,
    ]);
});

test('lista las ausencias del titular que pueden motivarla', function () {
    $vacaciones = ($this->vacaciones)();
    ($this->vacaciones)('pendiente');
    $comision = ($this->comision)();

    $lista = $this->getJson("/api/v1/expediente/subrogaciones/ausencias-del-titular?servidor_id={$this->titular->id}")
        ->assertOk()
        ->json('datos');

    expect(collect($lista)->map(fn ($a) => [$a['tipo'], $a['id'], $a['motivo']])->all())->toBe([
        ['accion_personal', $comision->id, 'comision_servicios'],
        ['vacacion', $vacaciones->id, 'vacaciones'],
    ]);
});

test('cabe dentro de la ausencia, y el motivo sale de ella', function () {
    $vacaciones = ($this->vacaciones)();
    $ausencia = ['ausencia_tipo' => 'vacacion', 'ausencia_id' => $vacaciones->id];

    $subrogacion = ($this->subrogar)($ausencia);
    expect($subrogacion->motivo->value)->toBe('vacaciones')
        ->and($subrogacion->ausencia_tipo->value)->toBe('vacacion')
        ->and($subrogacion->ausencia_id)->toBe($vacaciones->id);

    expect(fn () => ($this->subrogar)($ausencia, ['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-10']))
        ->toThrow(ReglaNegocioException::class, 'cabe dentro de la ausencia del titular');
});

test('sin ausencia no se subroga, y la que no está en el SGTH pide la resolución', function () {
    expect(fn () => ($this->subrogar)([]))->toThrow(ReglaNegocioException::class, 'Indique la ausencia del titular');
    expect(fn () => ($this->subrogar)(['ausencia_tipo' => 'otra']))->toThrow(ReglaNegocioException::class, 'resolución');
    expect(($this->subrogar)(['ausencia_tipo' => 'otra', 'resolucion_numero' => 'RES-2026-040'])->motivo->value)->toBe('otro');

    // Las vacaciones de otra persona no motivan esta.
    $ajenas = Vacacion::create([
        'servidor_id' => ($this->servidor)()->id, 'folio' => 'VAC-2026-0999', 'motivo' => 'vacaciones_anuales',
        'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-02', 'dias_solicitados' => 15,
        'tipo_dias' => 'calendario', 'estado' => 'aprobada',
    ]);
    expect(fn () => ($this->subrogar)(['ausencia_tipo' => 'vacacion', 'ausencia_id' => $ajenas->id], ['fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-25']))
        ->toThrow(ReglaNegocioException::class, 'no es del titular');

    // Por la API, la ausencia es obligatoria en la subrogación.
    $this->postJson('/api/v1/expediente/subrogaciones', [
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => ($this->servidor)()->id,
        'servidor_subrogado_id' => $this->titular->id, 'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $this->puesto->id, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-05',
    ])->assertStatus(422)->assertJsonValidationErrors(['ausencia_tipo'], 'errores');
});

test('anular las vacaciones del titular cancela la subrogación', function () {
    $vacaciones = ($this->vacaciones)();
    $subrogacion = ($this->subrogar)(['ausencia_tipo' => 'vacacion', 'ausencia_id' => $vacaciones->id]);

    app(VacacionService::class)->anular($vacaciones->id, 'Cambió de fechas.', $this->user);

    expect($subrogacion->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA)
        ->and($subrogacion->fresh()->observacion)->toContain('Se anularon las vacaciones')
        ->and($subrogacion->fresh()->movimientoPersonal->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

test('anular la licencia o la comisión también', function () {
    $licencia = Licencia::create([
        'folio' => 'LIC-2026-00050', 'servidor_id' => $this->titular->id, 'causa' => 'enfermedad',
        'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-15', 'dias' => 28,
        'documento_ruta' => 'asistencia/licencias/x.pdf', 'documento_nombre' => 'x.pdf',
    ]);
    $porLicencia = ($this->subrogar)(['ausencia_tipo' => 'licencia', 'ausencia_id' => $licencia->id]);

    app(LicenciaService::class)->anular($licencia->id, 'Registrada por error.', $this->user->id);
    expect($porLicencia->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);

    $comision = ($this->comision)();
    $porComision = ($this->subrogar)(['ausencia_tipo' => 'accion_personal', 'ausencia_id' => $comision->id]);
    expect($porComision->motivo->value)->toBe('comision_servicios');

    app(MovimientoPersonalStateService::class)->transicionar($comision, EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Mal registrada.']);
    expect($porComision->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);
});

test('el reintegro del titular la acorta hasta la víspera', function () {
    $comision = ($this->comision)();
    $subrogacion = ($this->subrogar)(
        ['ausencia_tipo' => 'accion_personal', 'ausencia_id' => $comision->id],
        ['fecha_fin' => '2026-12-10'],
    );

    $reintegro = MovimientoPersonal::create([
        'servidor_id' => $this->titular->id, 'tipo_movimiento' => TipoMovimientoPersonal::REINTEGRO->value,
        'estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0500',
        'descripcion' => 'Reintegro', 'fecha_efectiva' => '2026-11-16', 'movimiento_relacionado_id' => $comision->id,
    ]);

    expect($this->service->acortarPorReintegro($reintegro))->toBe(1)
        ->and($subrogacion->fresh()->fecha_fin->toDateString())->toBe('2026-11-15')
        ->and($subrogacion->fresh()->observacion)->toContain('AP-2026-0500')
        // Su acción, todavía borrador, dice el fin nuevo.
        ->and($subrogacion->fresh()->movimientoPersonal->fecha_fin->toDateString())->toBe('2026-11-15');
});
