<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

/**
 * `diffInYears()` devuelve un float desde Carbon 3, y el accesor lo entregaba
 * tal cual: el encabezado del expediente decía «9.034447289411942 años». Los
 * años de servicio son años cumplidos.
 *
 * Y se cuentan desde el ingreso a LA INSTITUCIÓN, en todos los regímenes. La
 * antigüedad en el sector público es otra cosa —de ella cuelgan los días de
 * vacaciones en LOSEP— y vive en PeriodoVacacionService, que este accesor ya
 * no espeja.
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

test('en LOSEP también cuenta desde el ingreso a la institución', function () {
    // Contaba desde el sector público, y como el accesor solo se muestra en la
    // ficha, un servidor LOSEP veía 16 años en pantalla y 6 en su certificado
    // laboral. La UATH confirmó el 2026-09-26 que lo que se certifica es el
    // tiempo en la institución, así que manda esa fecha.
    Carbon::setTestNow('2026-09-14');

    $servidor = servidorConIngreso([
        'regimen_laboral'              => 'losep',
        'fecha_ingreso_sector_publico' => '2010-03-15',
        'fecha_ingreso_institucion'    => '2020-01-10',
    ]);

    expect($servidor->anios_servicio)->toBe(6);
});

test('sin fecha de ingreso a la institución no hay cifra, aunque haya sector público', function () {
    $servidor = servidorConIngreso([
        'regimen_laboral'              => 'losep',
        'fecha_ingreso_sector_publico' => '2010-03-15',
    ]);

    expect($servidor->anios_servicio)->toBeNull();
});

test('sin fecha de referencia no hay años de servicio', function () {
    $servidor = servidorConIngreso(['regimen_laboral' => 'losep']);

    expect($servidor->anios_servicio)->toBeNull();
});
