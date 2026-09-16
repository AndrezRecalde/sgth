<?php

/*
| La tarea programada que sigue las fechas del viaje.
|
| «En comisión» y «pendiente de liquidación» dependían de un botón. Si
| Financiero no lo pulsaba, el viático seguía «aprobado» después de volver y el
| plazo de 5 días hábiles para liquidar nunca empezaba.
|
| Decidido con el usuario:
| - llegada la salida, lo aprobado y lo que tiene anticipo pasa a en comisión;
|   si el anticipo no se entregó, sale como un viático sin anticipo;
| - una solicitud sin aprobar no se toca;
| - llegado el regreso, lo que está en comisión pasa a pendiente de liquidación.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoHistorialEstado;
use App\Services\Viatico\ViaticoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Martes 6 de octubre de 2026, 10:00.
    Carbon::setTestNow('2026-10-06 10:00:00');

    $unidad = unidadDePrueba();
    $this->servidor = Servidor::create([
        'cedula'                   => '0800007001',
        'nombre'                   => 'Aurora',
        'apellido'                 => 'Automática',
        'puesto_id'                => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $this->viatico = fn (EstadoViatico $estado, string $salida, string $llegada, array $extra = []) =>
        Viatico::create(array_merge([
            'servidor_id'        => $this->servidor->id,
            'zona'               => 'dentro_provincia',
            'datetime_salida'    => $salida,
            'datetime_llegada'   => $llegada,
            'noches'             => 2,
            'justificacion'      => 'Supervisión de obras',
            'estado'             => $estado,
            'monto_calculado'    => 160,
            'monto_anticipo'     => 0,
            'modalidad_anticipo' => 'total',
        ], $extra));

    $this->correr = fn (?string $ahora = null) => $this->artisan(
        'sgth:viaticos:avanzar-estados', $ahora ? ['--ahora' => $ahora] : []
    )->assertSuccessful();
});

afterEach(fn () => Carbon::setTestNow());

it('llegada la salida, lo que tiene anticipo o no lo lleva pasa a en comisión', function () {
    $conAnticipo = ($this->viatico)(EstadoViatico::CON_ANTICIPO, '2026-10-06 08:00', '2026-10-07 18:00', ['monto_anticipo' => 112]);
    $sinAnticipo = ($this->viatico)(EstadoViatico::APROBADO, '2026-10-06 09:59', '2026-10-07 18:00', ['modalidad_anticipo' => 'sin_anticipo']);
    $manana = ($this->viatico)(EstadoViatico::CON_ANTICIPO, '2026-10-07 08:00', '2026-10-08 18:00');

    ($this->correr)();

    expect($conAnticipo->fresh()->estado)->toBe(EstadoViatico::EN_COMISION)
        ->and((float) $conAnticipo->fresh()->monto_anticipo)->toBe(112.0)
        ->and($sinAnticipo->fresh()->estado)->toBe(EstadoViatico::EN_COMISION)
        ->and($manana->fresh()->estado)->toBe(EstadoViatico::CON_ANTICIPO);

    $paso = ViaticoHistorialEstado::where('viatico_id', $conAnticipo->id)->sole();
    expect($paso->usuario_id)->toBeNull()
        ->and($paso->estado_anterior)->toBe('con_anticipo')
        ->and($paso->motivo)->toBe('Comenzó la comisión.');
});

it('si el anticipo no se entregó, sale como un viático sin anticipo', function () {
    $viatico = ($this->viatico)(EstadoViatico::APROBADO, '2026-10-06 08:00', '2026-10-07 18:00');

    ($this->correr)();

    expect($viatico->fresh())
        ->estado->toBe(EstadoViatico::EN_COMISION)
        ->modalidad_anticipo->toBe('sin_anticipo');

    expect(ViaticoHistorialEstado::where('viatico_id', $viatico->id)->sole()->motivo)
        ->toContain('sin que se entregara el anticipo');
});

it('una solicitud sin aprobar no se toca, ni lo cerrado', function (EstadoViatico $estado) {
    $viatico = ($this->viatico)($estado, '2026-10-01 08:00', '2026-10-02 18:00');

    ($this->correr)();

    expect($viatico->fresh()->estado)->toBe($estado)
        ->and(ViaticoHistorialEstado::count())->toBe(0);
})->with([
    'solicitado'    => [EstadoViatico::SOLICITADO],
    'rechazado'     => [EstadoViatico::RECHAZADO],
    'cancelado'     => [EstadoViatico::CANCELADO],
    'liquidado'     => [EstadoViatico::LIQUIDADO],
    'contabilizado' => [EstadoViatico::CONTABILIZADO],
]);

it('llegado el regreso, lo que está en comisión pasa a pendiente de liquidación', function () {
    $volvio = ($this->viatico)(EstadoViatico::EN_COMISION, '2026-10-04 08:00', '2026-10-06 09:30');
    $sigue = ($this->viatico)(EstadoViatico::EN_COMISION, '2026-10-05 08:00', '2026-10-06 18:00');

    ($this->correr)();

    expect($volvio->fresh()->estado)->toBe(EstadoViatico::PENDIENTE_LIQUIDACION)
        ->and($sigue->fresh()->estado)->toBe(EstadoViatico::EN_COMISION)
        ->and(ViaticoHistorialEstado::where('viatico_id', $volvio->id)->sole()->motivo)
        ->toContain('corre el plazo para liquidar');
});

it('un viaje que empezó y terminó desde la última corrida da los dos pasos, y repetir no hace nada', function () {
    $viatico = ($this->viatico)(EstadoViatico::CON_ANTICIPO, '2026-10-05 08:00', '2026-10-05 16:00');

    ($this->correr)();
    ($this->correr)();

    expect($viatico->fresh()->estado)->toBe(EstadoViatico::PENDIENTE_LIQUIDACION)
        ->and(ViaticoHistorialEstado::where('viatico_id', $viatico->id)->pluck('estado_nuevo')->all())
        ->toBe(['en_comision', 'pendiente_liquidacion']);
});

it('respeta el momento de corte que se le pasa', function () {
    $viatico = ($this->viatico)(EstadoViatico::CON_ANTICIPO, '2026-10-08 08:00', '2026-10-09 18:00');

    ($this->correr)('2026-10-08 07:59');
    expect($viatico->fresh()->estado)->toBe(EstadoViatico::CON_ANTICIPO);

    ($this->correr)('2026-10-08 08:00');
    expect($viatico->fresh()->estado)->toBe(EstadoViatico::EN_COMISION);
});

it('con la tarea, el plazo de liquidación empieza a correr solo', function () {
    // Volvió el lunes 5 y nadie pulsó ningún botón.
    ($this->viatico)(EstadoViatico::CON_ANTICIPO, '2026-10-03 08:00', '2026-10-05 18:00');
    $servicio = app(ViaticoService::class);

    ($this->correr)();
    expect($servicio->verificarBloqueo($this->servidor->id))->toBeFalse();

    // Cinco días hábiles después del regreso ya está fuera de plazo.
    Carbon::setTestNow('2026-10-13 10:00:00');
    expect($servicio->verificarBloqueo($this->servidor->id))->toBeTrue();
});
