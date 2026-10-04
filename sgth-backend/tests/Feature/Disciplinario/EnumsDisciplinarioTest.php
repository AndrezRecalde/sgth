<?php

namespace Tests\Feature\Disciplinario;

use App\Enums\CausalVistoBueno;
use App\Enums\EstadoSumario;
use App\Enums\EstadoVistoBueno;
use App\Enums\TipoFalta;
use App\Enums\TipoSancion;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Las etiquetas de los enums del módulo están espejadas en
 * `features/disciplinario/utils/etiquetas.ts`, porque el API devuelve el modelo
 * crudo y no un recurso con `*_label`.
 *
 * Mientras eso siga así, estas pruebas son la grapa: cambiar un texto aquí
 * rompe una prueba, y quien la arregle verá en el mensaje que tiene que
 * cambiar también el mapa del frontend. Sin ellas, las dos redacciones se
 * separan en silencio — que es lo que había pasado con tres de las siete
 * causales del Art. 172.
 *
 * El arreglo de fondo es un endpoint de catálogos como
 * `/dispensario/fichas-sso/catalogo-riesgos`, que sirve las etiquetas y deja
 * de haber dos listas. Anotado en la auditoría del módulo.
 */
$aviso = 'Si cambia este texto, cambie también su mapa en '
    .'sgth-frontend/src/features/disciplinario/utils/etiquetas.ts';

test('las etiquetas de los estados del sumario están fijadas', function () use ($aviso) {
    expect(array_map(
        fn (EstadoSumario $e) => $e->etiqueta(),
        EstadoSumario::cases()
    ))->toBe([
        'Abierto',
        'En instrucción',
        'En prueba',
        'Con informe',
        'Resuelto',
        'Apelado',
        'Cerrado',
    ], $aviso);
});

test('las etiquetas de los estados del visto bueno están fijadas', function () use ($aviso) {
    expect(array_map(
        fn (EstadoVistoBueno $e) => $e->etiqueta(),
        EstadoVistoBueno::cases()
    ))->toBe([
        'Solicitado',
        'Notificado al trabajador',
        'En investigación',
        'Concedido',
        'Negado',
        'Desistido',
        'Impugnado',
    ], $aviso);
});

test('las etiquetas de las sanciones y las faltas están fijadas', function () use ($aviso) {
    expect(array_map(fn (TipoFalta $f) => $f->etiqueta(), TipoFalta::cases()))
        ->toBe(['Leve', 'Grave'], $aviso);

    expect(array_map(fn (TipoSancion $s) => $s->etiqueta(), TipoSancion::cases()))
        ->toBe([
            'Amonestación verbal',
            'Amonestación escrita',
            'Multa',
            'Suspensión',
            'Destitución',
        ], $aviso);
});

test('las siete causales del Art. 172 conservan su numeral y su texto', function () use ($aviso) {
    $causales = array_map(
        fn (CausalVistoBueno $c) => [$c->numeral(), $c->etiqueta()],
        CausalVistoBueno::cases()
    );

    expect($causales)->toBe([
        [1, 'Faltas repetidas de puntualidad o asistencia, o abandono del trabajo'],
        [2, 'Indisciplina o desobediencia graves a los reglamentos internos'],
        [3, 'Falta de probidad o conducta inmoral'],
        [4, 'Injurias graves al empleador o su representante'],
        [5, 'Ineptitud manifiesta para la labor contratada'],
        [6, 'Denuncia injustificada contra el empleador ante el Seguro Social'],
        [7, 'No acatar las medidas de seguridad, prevención e higiene'],
    ], $aviso);
});

test('el numeral no se repite ni se salta', function () {
    $numerales = array_map(fn (CausalVistoBueno $c) => $c->numeral(), CausalVistoBueno::cases());

    expect($numerales)->toBe(range(1, 7));
});

test('la referencia legal se arma con el numeral', function () {
    expect(CausalVistoBueno::FALTA_PROBIDAD->referenciaLegal())
        ->toBe('Art. 172 núm. 3 del Código del Trabajo');
});

test('las sanciones de cada gravedad son las del Art. 42 de la LOSEP', function () use ($aviso) {
    // Espejadas por SANCIONES_POR_FALTA en el frontend.
    $mapa = [];
    foreach (TipoFalta::cases() as $falta) {
        $mapa[$falta->value] = array_map(fn (TipoSancion $s) => $s->value, $falta->sancionesAdmitidas());
    }

    expect($mapa)->toBe([
        'leve'  => ['amonestacion_verbal', 'amonestacion_escrita', 'multa'],
        'grave' => ['suspension', 'destitucion'],
    ], $aviso);
});
