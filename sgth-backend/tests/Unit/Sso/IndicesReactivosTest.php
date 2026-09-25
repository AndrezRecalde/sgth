<?php

use App\Services\Sso\Indicadores\IndicesReactivos;

/**
 * Los índices reactivos que se informan al IESS (CD 513).
 *
 * Son tres divisiones, y justo por eso nadie las revisa: un 200 000 mal puesto
 * o un IF y un IG cambiados de sitio dan un número plausible que acaba en un
 * informe. Sin base de datos: la fórmula no la necesita.
 */

test('las horas de referencia son 200 000', function () {
    expect(IndicesReactivos::HORAS_REFERENCIA)->toBe(200000);
});

test('IF y IG se calculan sobre las horas trabajadas', function () {
    // 4 lesiones y 60 días perdidos en 500 000 horas.
    $indices = IndicesReactivos::desde(4, 60, 500_000);

    expect($indices->indiceFrecuencia)->toBe(1.6);   // 4 × 200000 / 500000
    expect($indices->indiceGravedad)->toBe(24.0);    // 60 × 200000 / 500000
    expect($indices->tasaRiesgo)->toBe(15.0);        // 24 / 1.6 = 60 / 4
});

test('la tasa de riesgo son los días perdidos por lesión', function () {
    // TR = IG / IF, que se simplifica a días / lesiones: el denominador se va.
    $indices = IndicesReactivos::desde(3, 18, 123_456);

    expect($indices->tasaRiesgo)->toBe(6.0);   // 18 / 3
});

test('sin lesiones los tres índices son cero', function () {
    $indices = IndicesReactivos::desde(0, 0, 400_000);

    expect($indices->indiceFrecuencia)->toBe(0.0);
    expect($indices->indiceGravedad)->toBe(0.0);
    // No hay lesiones que dividan: la tasa es 0 y no una división por cero.
    expect($indices->tasaRiesgo)->toBe(0.0);
});

test('días perdidos sin lesiones registradas no divide por cero', function () {
    // Dato inconsistente —reposo sin accidente con lesión en el período— que
    // el sistema puede tener mientras alguien no lo corrija.
    $indices = IndicesReactivos::desde(0, 12, 400_000);

    expect($indices->indiceFrecuencia)->toBe(0.0);
    expect($indices->indiceGravedad)->toBe(6.0);
    expect($indices->tasaRiesgo)->toBe(0.0);
});

test('los índices se redondean a dos decimales', function () {
    // 1 × 200000 / 7000 = 28.5714…
    $indices = IndicesReactivos::desde(1, 5, 7_000);

    expect($indices->indiceFrecuencia)->toBe(28.57);
    expect($indices->indiceGravedad)->toBe(142.86);
    expect($indices->tasaRiesgo)->toBe(5.0);
});

test('sin horas trabajadas no hay índice que calcular', function () {
    // Quien llama distingue ese caso antes ('sin_datos'), porque tiene que
    // decir qué falta cargar; aquí se corta para que no pase inadvertido.
    expect(fn() => IndicesReactivos::desde(2, 10, 0))
        ->toThrow(InvalidArgumentException::class);
});
