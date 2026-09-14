<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

/**
 * `diffInYears()` devuelve un float desde Carbon 3, y el accesor lo entregaba
 * tal cual: el encabezado del expediente decía «9.034447289411942 años». Los
 * años de servicio son años cumplidos, el mismo entero que ya usan
 * `PeriodoVacacionService::calcularAntiguedad` y el motor de vacaciones del
 * Código del Trabajo.
 *
 * No toca la base: el accesor solo lee atributos del modelo.
 */
afterEach(fn () => Carbon::setTestNow());

function servidorConIngreso(array $atributos): Servidor
{
    return (new Servidor())->forceFill($atributos);
}

test('los años de servicio son años cumplidos, enteros', function () {
    Carbon::setTestNow('2026-09-14');

    $servidor = servidorConIngreso([
        'regimen_laboral'           => 'codigo_trabajo',
        'fecha_ingreso_institucion' => '2017-09-01',
    ]);

    expect($servidor->anios_servicio)->toBe(9);
});

test('un día antes del aniversario todavía no suma el año', function () {
    Carbon::setTestNow('2026-08-31');

    $servidor = servidorConIngreso([
        'regimen_laboral'           => 'codigo_trabajo',
        'fecha_ingreso_institucion' => '2017-09-01',
    ]);

    expect($servidor->anios_servicio)->toBe(8);
});

test('en LOSEP cuenta desde el ingreso al sector público', function () {
    Carbon::setTestNow('2026-09-14');

    $servidor = servidorConIngreso([
        'regimen_laboral'              => 'losep',
        'fecha_ingreso_sector_publico' => '2010-03-15',
        'fecha_ingreso_institucion'    => '2020-01-10',
    ]);

    expect($servidor->anios_servicio)->toBe(16);
});

test('sin fecha de referencia no hay años de servicio', function () {
    $servidor = servidorConIngreso(['regimen_laboral' => 'losep']);

    expect($servidor->anios_servicio)->toBeNull();
});
