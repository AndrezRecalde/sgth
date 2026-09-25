<?php

use App\Exceptions\ReglaNegocioException;
use App\Services\Sso\PeriodoSso;

/**
 * El período del módulo SSO: '2026' o '2026-07'.
 *
 * Lo usan siete controladores para validar y dos servicios para acotar por
 * fechas. Mientras estuvo copiado, la expresión aceptaba `2026-13` y `Carbon`
 * lo desbordaba en silencio a enero de 2027: el informe salía con los datos de
 * un mes que no existe y nada avisaba.
 */

test('un año y un mes válidos se aceptan', function () {
    expect(PeriodoSso::esValido('2026'))->toBeTrue();
    expect(PeriodoSso::esValido('2026-01'))->toBeTrue();
    expect(PeriodoSso::esValido('2026-12'))->toBeTrue();
});

dataset('periodos_invalidos', [
    'mes 13'            => ['2026-13'],
    'mes 00'            => ['2026-00'],
    'mes de una cifra'  => ['2026-7'],
    'sin guion'         => ['202607'],
    'texto'             => ['abcd'],
    'año de tres cifras'=> ['202-01'],
    'vacío'             => [''],
]);

test('lo que no es un período se rechaza', function (string $periodo) {
    expect(PeriodoSso::esValido($periodo))->toBeFalse();
})->with('periodos_invalidos');

test('el rango de un año va del 1 de enero al 31 de diciembre', function () {
    [$inicio, $fin] = PeriodoSso::rango('2026');

    expect($inicio->toDateString())->toBe('2026-01-01');
    expect($fin->toDateString())->toBe('2026-12-31');
});

test('el rango de un mes cubre el mes entero', function () {
    [$inicio, $fin] = PeriodoSso::rango('2026-02');

    expect($inicio->toDateString())->toBe('2026-02-01');
    // 2026 no es bisiesto: febrero termina el 28.
    expect($fin->toDateString())->toBe('2026-02-28');
});

test('el rango llega al final del día, no al principio', function () {
    // Los accidentes se comparan con `whereBetween` contra una columna de
    // fecha: si el fin fuera a las 00:00, el último día del período quedaría
    // fuera en cuanto la columna llevara hora.
    [, $fin] = PeriodoSso::rango('2026-07');

    expect($fin->format('H:i:s'))->toBe('23:59:59');
});

test('un período con mes imposible no se convierte en otro', function () {
    expect(fn() => PeriodoSso::rango('2026-13'))->toThrow(ReglaNegocioException::class);
});

test('esAnio distingue el año del mes', function () {
    expect(PeriodoSso::esAnio('2026'))->toBeTrue();
    expect(PeriodoSso::esAnio('2026-07'))->toBeFalse();
});

test('las reglas de validación llevan la misma expresión', function () {
    expect(PeriodoSso::reglas())->toBe(['required', 'string', 'regex:/'.PeriodoSso::PATRON.'/']);
    expect(PeriodoSso::reglasOpcionales()[0])->toBe('nullable');
});
