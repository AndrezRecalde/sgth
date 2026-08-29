<?php

use App\Catalogos\FactoresRiesgoMsp;
use App\Enums\CategoriaRiesgoLaboral;

/**
 * El catálogo reproduce la sección G del formulario SNS-MSP/HCU-form.123/2025.
 * Estas pruebas son un candado: si alguien "mejora" un nombre o agrega un
 * factor que el impreso no tiene, el PDF deja de cuadrar con el oficial.
 */
test('el catálogo tiene los 61 factores del formulario del MSP', function () {
    expect(FactoresRiesgoMsp::total())->toBe(61);
});

test('el reparto por categoría es el del impreso', function () {
    $esperado = [
        'fisico' => 10,
        'seguridad' => 15,
        'quimico' => 9,
        'biologico' => 7,
        'ergonomico' => 6,
        'psicosocial' => 14,
    ];

    foreach ($esperado as $categoria => $cuantos) {
        expect(FactoresRiesgoMsp::factoresDe($categoria))
            ->toHaveCount($cuantos, "categoría {$categoria}");
    }
});

test('las categorías del catálogo son exactamente las del enum', function () {
    $delCatalogo = array_keys(FactoresRiesgoMsp::catalogo());
    $delEnum = array_column(CategoriaRiesgoLaboral::cases(), 'value');

    sort($delCatalogo);
    sort($delEnum);

    expect($delCatalogo)->toBe($delEnum);
});

test('solo «de seguridad» se subdivide, y en sus cuatro bloques', function () {
    $catalogo = FactoresRiesgoMsp::catalogo();

    expect(array_column($catalogo['seguridad']['grupos'], 'subcategoria'))
        ->toBe(['locativos', 'mecanicos', 'electricos', 'otros']);

    foreach ($catalogo as $clave => $categoria) {
        if ($clave === 'seguridad') {
            continue;
        }
        expect($categoria['grupos'])->toHaveCount(1, "categoría {$clave}")
            ->and($categoria['grupos'][0]['subcategoria'])->toBeNull();
    }
});

test('la subcategoría se deduce del factor', function () {
    expect(FactoresRiesgoMsp::subcategoriaDe('seguridad', 'Cortes'))->toBe('mecanicos')
        ->and(FactoresRiesgoMsp::subcategoriaDe('seguridad', 'Contacto eléctrico'))->toBe('electricos')
        ->and(FactoresRiesgoMsp::subcategoriaDe('seguridad', 'Falta de señalización, aseo, desorden'))->toBe('locativos')
        ->and(FactoresRiesgoMsp::subcategoriaDe('fisico', 'Ruido'))->toBeNull()
        ->and(FactoresRiesgoMsp::subcategoriaDe('fisico', 'Factor que no existe'))->toBeNull();
});

test('el MSP separa temperaturas altas y bajas en dos filas', function () {
    // El catálogo anterior las fundía en «Temperatura extrema», que no existe
    // en el formulario. Es el ejemplo de por qué esto se prueba.
    $fisico = FactoresRiesgoMsp::factoresDe('fisico');

    expect($fisico)->toContain('Temperaturas altas')
        ->and($fisico)->toContain('Temperaturas bajas')
        ->and($fisico)->not->toContain('Temperatura extrema');
});

test('no quedan factores inventados del catálogo anterior', function () {
    $inventados = [
        'Trabajo en alturas',
        'Espacios confinados',
        'Presión anormal',
        'Trabajo nocturno',
        'Fluidos corporales',
        'Trabajo de pie prolongado',
        'Iluminación deficiente',
        'Carga física',
    ];

    $todos = FactoresRiesgoMsp::todosLosFactores();

    foreach ($inventados as $factor) {
        expect($todos)->not->toContain($factor);
    }
});

test('cada categoría ofrece «Otros», como el impreso', function () {
    // `toContain` de Pest es variádico: un segundo argumento sería otro valor
    // a buscar, no un mensaje. La categoría se nombra en el propio expect.
    foreach (array_keys(FactoresRiesgoMsp::catalogo()) as $categoria) {
        expect([$categoria => FactoresRiesgoMsp::factoresDe($categoria)])
            ->toHaveKey($categoria)
            ->and(FactoresRiesgoMsp::factoresDe($categoria))
            ->toContain('Otros');
    }
});
