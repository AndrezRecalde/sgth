<?php

use App\Services\Sso\Indicadores\HorasTrabajadas;

/**
 * El denominador de los índices reactivos CD 513.
 *
 * La regla que fija esta prueba es la que faltaba: `horas_trabajadas_periodo`
 * guarda el período como cadena ('2026' o '2026-07') y la unidad como columna
 * nullable, de modo que una igualdad exacta en los dos ejes dejaba el índice
 * ANUAL —el que se informa al IESS— sin denominador aunque los doce meses
 * estuvieran cargados. Son dos ejes con precedencia, y ninguno es evidente
 * leyendo el cálculo.
 *
 * Sin base de datos a propósito: la decisión es pura.
 */
function filaHoras(string $periodo, ?int $unidad, int $horas): array
{
    return ['periodo' => $periodo, 'unidad_administrativa_id' => $unidad, 'total_horas' => $horas];
}

test('sin filas no hay denominador', function () {
    $resolucion = HorasTrabajadas::desde(collect(), '2026', null);

    expect($resolucion->hay())->toBeFalse();
    expect($resolucion->horas)->toBe(0);
    expect($resolucion->origen)->toBeNull();
    expect($resolucion->detalle())->toBeNull();
});

test('la fila del período pedido manda', function () {
    $resolucion = HorasTrabajadas::desde(
        collect([filaHoras('2026', null, 1_200_000)]),
        '2026',
        null,
    );

    expect($resolucion->horas)->toBe(1_200_000);
    expect($resolucion->origen)->toBe(HorasTrabajadas::ORIGEN_EXACTO);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_INSTITUCIONAL);
});

test('un período anual sin su fila se compone de sus meses', function () {
    $meses = collect(range(1, 12))->map(
        fn(int $mes) => filaHoras(sprintf('2026-%02d', $mes), null, 100_000),
    );

    $resolucion = HorasTrabajadas::desde($meses, '2026', null);

    expect($resolucion->horas)->toBe(1_200_000);
    expect($resolucion->origen)->toBe(HorasTrabajadas::ORIGEN_MESES);
    expect($resolucion->meses)->toBe(12);
    expect($resolucion->detalle())->toContain('la suma de 12 meses');
});

test('la fila anual gana a la suma de sus meses cuando existen las dos', function () {
    $filas = collect([
        filaHoras('2026', null, 1_150_000),
        filaHoras('2026-01', null, 100_000),
        filaHoras('2026-02', null, 100_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', null);

    expect($resolucion->horas)->toBe(1_150_000);
    expect($resolucion->origen)->toBe(HorasTrabajadas::ORIGEN_EXACTO);
});

test('un período mensual no se saca del total del año', function () {
    // Repartir un total anual entre doce meses sería inventar el dato, así que
    // el mes sin su propia fila se queda sin denominador aunque el año esté
    // cargado.
    $resolucion = HorasTrabajadas::desde(
        collect([filaHoras('2026', null, 1_200_000)]),
        '2026-07',
        null,
    );

    expect($resolucion->hay())->toBeFalse();
    expect($resolucion->origen)->toBeNull();
});

test('el total institucional manda sobre las filas por unidad', function () {
    // Sumar las dos cosas contaría dos veces las mismas horas.
    $filas = collect([
        filaHoras('2026', null, 1_200_000),
        filaHoras('2026', 3, 400_000),
        filaHoras('2026', 7, 500_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', null);

    expect($resolucion->horas)->toBe(1_200_000);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_INSTITUCIONAL);
});

test('sin total institucional se suman las unidades', function () {
    // El numerador (las lesiones) es de toda la institución: un denominador
    // parcial infla el índice, así que se toman todas las unidades cargadas.
    $filas = collect([
        filaHoras('2026', 3, 400_000),
        filaHoras('2026', 7, 500_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', null);

    expect($resolucion->horas)->toBe(900_000);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_SUMA_UNIDADES);
    expect($resolucion->unidades)->toBe(2);
    expect($resolucion->detalle())->toContain('sumando 2 unidades');
});

test('con unidad consultada el alcance es esa unidad', function () {
    $resolucion = HorasTrabajadas::desde(
        collect([filaHoras('2026', 3, 400_000)]),
        '2026',
        3,
    );

    expect($resolucion->horas)->toBe(400_000);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_UNIDAD);
    expect($resolucion->detalle())->toContain('la unidad consultada');
});

test('con unidad consultada se descartan las filas de las demás', function () {
    // La consulta ya filtra por unidad; la clase lo repite para no depender de
    // que quien la llama lo recuerde.
    $filas = collect([
        filaHoras('2026', 3, 400_000),
        filaHoras('2026', 7, 500_000),
        filaHoras('2026', null, 1_200_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', 3);

    expect($resolucion->horas)->toBe(400_000);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_UNIDAD);
});

test('una unidad sin horas cargadas no hereda las de otra', function () {
    $resolucion = HorasTrabajadas::desde(
        collect([filaHoras('2026', 7, 500_000)]),
        '2026',
        3,
    );

    expect($resolucion->hay())->toBeFalse();
});

test('el alcance se decide antes que el período', function () {
    // El institucional solo tiene meses; una unidad tiene el año entero. Manda
    // el alcance institucional, y dentro de él se compone el año con sus meses:
    // mezclar los dos alcances daría un denominador que no es de nadie.
    $filas = collect([
        filaHoras('2026-01', null, 100_000),
        filaHoras('2026-02', null, 100_000),
        filaHoras('2026', 3, 900_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', null);

    expect($resolucion->horas)->toBe(200_000);
    expect($resolucion->origen)->toBe(HorasTrabajadas::ORIGEN_MESES);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_INSTITUCIONAL);
});

test('los meses de varias unidades se suman en los dos ejes', function () {
    $filas = collect([
        filaHoras('2026-01', 3, 50_000),
        filaHoras('2026-02', 3, 50_000),
        filaHoras('2026-01', 7, 60_000),
        filaHoras('2026-02', 7, 60_000),
    ]);

    $resolucion = HorasTrabajadas::desde($filas, '2026', null);

    expect($resolucion->horas)->toBe(220_000);
    expect($resolucion->origen)->toBe(HorasTrabajadas::ORIGEN_MESES);
    expect($resolucion->alcance)->toBe(HorasTrabajadas::ALCANCE_SUMA_UNIDADES);
    expect($resolucion->meses)->toBe(2);
    expect($resolucion->unidades)->toBe(2);
});

test('esAnio distingue el año del mes', function () {
    expect(HorasTrabajadas::esAnio('2026'))->toBeTrue();
    expect(HorasTrabajadas::esAnio('2026-07'))->toBeFalse();
});
