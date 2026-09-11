<?php

/*
| La escala de días de vacaciones, confirmada con Talento Humano el 2026-09-11.
|
| - LOSEP, art. 29: treinta días calendario al año, sin escala por antigüedad,
|   completos desde el primer año. Se aplicaba 15/20/25/30.
| - Código del Trabajo, art. 69: quince, y uno más por cada año que exceda de
|   cinco, hasta treinta. Los períodos lo sumaban desde el segundo año.
|
| Y el comando que recalcula los períodos abiertos con esa escala.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\EscalaVacaciones;
use App\Services\Asistencia\VacacionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

function servidorParaEscala(string $cedula, RegimenLaboral $regimen, string $ingreso): Servidor
{
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $unidad = unidadDePrueba();

    return Servidor::create([
        'cedula'                       => $cedula,
        'nombre'                       => 'Elena',
        'apellido'                     => 'Escala',
        'puesto_id'                    => puestoDePrueba($unidad)->id,
        'unidad_administrativa_id'     => $unidad->id,
        'regimen_laboral'              => $regimen,
        'fecha_ingreso_sector_publico' => $ingreso,
        'fecha_ingreso_institucion'    => $ingreso,
        'estado'                       => true,
    ]);
}

/** Un período como lo dejaba la escala anterior. */
function periodoConEscalaAnterior(
    Servidor $servidor,
    int $anio,
    float $generados,
    float $utilizados = 0,
    string $estado = 'abierto',
): PeriodoVacacion {
    return PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => $servidor->regimen_laboral->value,
        'anios_antiguedad'     => 3,
        'dias_generados'       => $generados,
        'dias_utilizados'      => $utilizados,
        'dias_saldo'           => $generados - $utilizados,
        'saldo_acumulado'      => $generados - $utilizados,
        'estado'               => $estado,
    ]);
}

// ── La escala ────────────────────────────────────────────────────────

test('la LOSEP genera 30 días al año, sin escala por antigüedad', function (int $anios) {
    expect(EscalaVacaciones::diasGenerados('losep', $anios))->toBe(30.0);
})->with([0, 1, 5, 6, 11, 16, 30]);

test('el Código del Trabajo suma un día por cada año que exceda de cinco, hasta 30', function (int $anios, float $dias) {
    expect(EscalaVacaciones::diasGenerados('codigo_trabajo', $anios))->toBe($dias);
})->with([
    'un año'      => [1, 15.0],
    'dos años'    => [2, 15.0],
    'tres años'   => [3, 15.0],
    'cinco años'  => [5, 15.0],
    'seis años'   => [6, 16.0],
    'diez años'   => [10, 20.0],
    'quince años' => [15, 25.0],
    'veinte años' => [20, 30.0],
    'treinta'     => [30, 30.0],
]);

test('servicios profesionales no genera vacaciones', function () {
    expect(EscalaVacaciones::diasGenerados('servicios_profesionales', 10))->toBe(0.0);
});

test('la LOSEP descuenta días calendario, fin de semana incluido', function () {
    $motor = (new VacacionService())->obtenerMotor(
        new Servidor(['regimen_laboral' => RegimenLaboral::LOSEP])
    );

    // Del jueves 1 al lunes 5 de octubre de 2026: cinco días calendario.
    $jueves = Carbon::parse('2026-10-01');

    expect($motor->calcularDiasDescuento($jueves, $jueves->copy()->addDays(4)))->toBe(5.0);
});

// ── El recálculo de los períodos abiertos ────────────────────────────

test('aplicar la escala recalcula los períodos abiertos y conserva lo gozado', function () {
    $losep = servidorParaEscala('0800007001', RegimenLaboral::LOSEP, '2023-01-01');
    $ct    = servidorParaEscala('0800007002', RegimenLaboral::CODIGO_TRABAJO, '2023-01-01');

    $periodoLosep = periodoConEscalaAnterior($losep, 2026, 15, 5);
    $periodoCt    = periodoConEscalaAnterior($ct, 2026, 17);

    $this->artisan('sgth:vacaciones:aplicar-escala-legal', ['--responsable' => 'Dirección de Talento Humano'])
        ->assertSuccessful();

    $periodoLosep->refresh();
    $periodoCt->refresh();

    // LOSEP: de 15 a 30; lo gozado sigue ahí.
    expect((float) $periodoLosep->dias_generados)->toBe(30.0)
        ->and((float) $periodoLosep->dias_utilizados)->toBe(5.0)
        ->and((float) $periodoLosep->dias_saldo)->toBe(25.0)
        // Código del Trabajo con 3 años: de 17 a 15.
        ->and((float) $periodoCt->dias_generados)->toBe(15.0);
});

test('un período cerrado no se toca', function () {
    $losep   = servidorParaEscala('0800007011', RegimenLaboral::LOSEP, '2020-01-01');
    $cerrado = periodoConEscalaAnterior($losep, 2025, 15, 0, 'cerrado');

    $this->artisan('sgth:vacaciones:aplicar-escala-legal', ['--responsable' => 'Talento Humano'])
        ->assertSuccessful();

    expect((float) $cerrado->fresh()->dias_generados)->toBe(15.0);
});

test('el acumulado de cada año se rehace con los saldos nuevos', function () {
    $losep = servidorParaEscala('0800007021', RegimenLaboral::LOSEP, '2020-01-01');
    periodoConEscalaAnterior($losep, 2025, 15);
    $actual = periodoConEscalaAnterior($losep, 2026, 15);

    $this->artisan('sgth:vacaciones:aplicar-escala-legal', ['--responsable' => 'Talento Humano'])
        ->assertSuccessful();

    // 30 del año anterior más 30 de este.
    expect((float) $actual->fresh()->saldo_acumulado)->toBe(60.0);
});

test('--simular muestra lo que cambiaría y no guarda nada', function () {
    $losep   = servidorParaEscala('0800007031', RegimenLaboral::LOSEP, '2022-01-01');
    $periodo = periodoConEscalaAnterior($losep, 2026, 15);

    $this->artisan('sgth:vacaciones:aplicar-escala-legal', ['--simular' => true])
        ->expectsOutputToContain('0800007031')
        ->assertSuccessful();

    expect((float) $periodo->fresh()->dias_generados)->toBe(15.0)
        ->and(DB::table('activity_log')->where('log_name', 'periodos-vacaciones')->count())->toBe(0);
});

test('sin responsable no se aplica nada', function () {
    $losep   = servidorParaEscala('0800007041', RegimenLaboral::LOSEP, '2022-01-01');
    $periodo = periodoConEscalaAnterior($losep, 2026, 15);

    $this->artisan('sgth:vacaciones:aplicar-escala-legal')->assertFailed();

    expect((float) $periodo->fresh()->dias_generados)->toBe(15.0);
});

test('cada período recalculado queda en la bitácora con el antes, el después y quién lo autorizó', function () {
    $losep = servidorParaEscala('0800007051', RegimenLaboral::LOSEP, '2022-01-01');
    periodoConEscalaAnterior($losep, 2026, 15);

    $this->artisan('sgth:vacaciones:aplicar-escala-legal', ['--responsable' => 'Dirección de Talento Humano'])
        ->assertSuccessful();

    $registro = DB::table('activity_log')->where('log_name', 'periodos-vacaciones')->latest('id')->first();
    $props    = json_decode($registro->properties, true);

    expect($props['responsable'])->toBe('Dirección de Talento Humano')
        ->and((float) $props['antes']['dias_generados'])->toBe(15.0)
        ->and((float) $props['despues']['dias_generados'])->toBe(30.0);
});
