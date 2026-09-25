<?php

use App\Enums\NivelConsecuenciasRiesgo;
use App\Enums\NivelDeficienciaRiesgo;
use App\Enums\NivelExposicionRiesgo;
use App\Enums\NivelIntervencionRiesgo;

/**
 * La valoración NTP 330 (INSHT): los valores de cada nivel y los cortes del
 * nivel de intervención.
 *
 * Es la tabla que decide si un riesgo se corrige de urgencia o no se toca, y
 * está copiada a mano en dos sitios —estos enums y su réplica en el cliente,
 * `riesgoLaboral.schema.ts`—. Una errata en un número no rompe nada: devuelve
 * un nivel de intervención más tranquilizador y nadie se enteraría.
 *
 * Sin base de datos: la valoración es aritmética sobre los enums.
 */

test('los valores de cada nivel son los de la NTP 330', function () {
    expect(NivelDeficienciaRiesgo::MUY_DEFICIENTE->valor())->toBe(10);
    expect(NivelDeficienciaRiesgo::DEFICIENTE->valor())->toBe(6);
    expect(NivelDeficienciaRiesgo::MEJORABLE->valor())->toBe(2);
    expect(NivelDeficienciaRiesgo::ACEPTABLE->valor())->toBe(0);

    expect(NivelExposicionRiesgo::CONTINUADA->valor())->toBe(4);
    expect(NivelExposicionRiesgo::FRECUENTE->valor())->toBe(3);
    expect(NivelExposicionRiesgo::OCASIONAL->valor())->toBe(2);
    expect(NivelExposicionRiesgo::ESPORADICA->valor())->toBe(1);

    expect(NivelConsecuenciasRiesgo::MORTAL_CATASTROFICO->valor())->toBe(100);
    expect(NivelConsecuenciasRiesgo::MUY_GRAVE->valor())->toBe(60);
    expect(NivelConsecuenciasRiesgo::GRAVE->valor())->toBe(25);
    expect(NivelConsecuenciasRiesgo::LEVE->valor())->toBe(10);
});

dataset('cortes_de_intervencion', [
    // Rangos oficiales: I (4000-600), II (500-150), III (120-40), IV (20 o menos).
    'tope de la escala'   => [4000, NivelIntervencionRiesgo::I],
    'I en su frontera'    => [600, NivelIntervencionRiesgo::I],
    'II justo debajo'     => [599, NivelIntervencionRiesgo::II],
    'II en su frontera'   => [150, NivelIntervencionRiesgo::II],
    'III justo debajo'    => [149, NivelIntervencionRiesgo::III],
    'III en su frontera'  => [40, NivelIntervencionRiesgo::III],
    'IV justo debajo'     => [39, NivelIntervencionRiesgo::IV],
    'IV en el suelo'      => [0, NivelIntervencionRiesgo::IV],
]);

test('el nivel de intervención sale del nivel de riesgo', function (
    int $nivelRiesgo,
    NivelIntervencionRiesgo $esperado,
) {
    expect(NivelIntervencionRiesgo::desdeNivelRiesgo($nivelRiesgo))->toBe($esperado);
})->with('cortes_de_intervencion');

dataset('valoraciones', [
    // ND × NE = NP, NP × NC = NR, y el nivel que le corresponde.
    'lo peor de la escala' => [
        NivelDeficienciaRiesgo::MUY_DEFICIENTE, NivelExposicionRiesgo::CONTINUADA,
        NivelConsecuenciasRiesgo::MORTAL_CATASTROFICO, 40, 4000, NivelIntervencionRiesgo::I,
    ],
    'deficiente y frecuente, muy grave' => [
        NivelDeficienciaRiesgo::DEFICIENTE, NivelExposicionRiesgo::FRECUENTE,
        NivelConsecuenciasRiesgo::MUY_GRAVE, 18, 1080, NivelIntervencionRiesgo::I,
    ],
    'mejorable y ocasional, grave' => [
        NivelDeficienciaRiesgo::MEJORABLE, NivelExposicionRiesgo::OCASIONAL,
        NivelConsecuenciasRiesgo::GRAVE, 4, 100, NivelIntervencionRiesgo::III,
    ],
    'mejorable y esporádica, leve' => [
        NivelDeficienciaRiesgo::MEJORABLE, NivelExposicionRiesgo::ESPORADICA,
        NivelConsecuenciasRiesgo::LEVE, 2, 20, NivelIntervencionRiesgo::IV,
    ],
    // ND aceptable vale 0: por muy grave que sea la consecuencia, el riesgo
    // valorado es 0. Es la escala oficial, y conviene que esté por escrito.
    'aceptable anula la probabilidad' => [
        NivelDeficienciaRiesgo::ACEPTABLE, NivelExposicionRiesgo::CONTINUADA,
        NivelConsecuenciasRiesgo::MORTAL_CATASTROFICO, 0, 0, NivelIntervencionRiesgo::IV,
    ],
]);

test('NP = ND × NE y NR = NP × NC', function (
    NivelDeficienciaRiesgo $deficiencia,
    NivelExposicionRiesgo $exposicion,
    NivelConsecuenciasRiesgo $consecuencias,
    int $probabilidadEsperada,
    int $riesgoEsperado,
    NivelIntervencionRiesgo $intervencionEsperada,
) {
    $probabilidad = $deficiencia->valor() * $exposicion->valor();
    $riesgo = $probabilidad * $consecuencias->valor();

    expect($probabilidad)->toBe($probabilidadEsperada);
    expect($riesgo)->toBe($riesgoEsperado);
    expect(NivelIntervencionRiesgo::desdeNivelRiesgo($riesgo))->toBe($intervencionEsperada);
})->with('valoraciones');

test('cada nivel de intervención tiene etiqueta', function () {
    foreach (NivelIntervencionRiesgo::cases() as $nivel) {
        expect($nivel->etiqueta())->not->toBeEmpty();
    }
});
