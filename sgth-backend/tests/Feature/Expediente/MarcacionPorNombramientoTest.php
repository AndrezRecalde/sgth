<?php

use App\Enums\TipoNombramiento;

/**
 * Hay modalidades que no marcan biométrico en ningún caso. No es un valor
 * sugerido sino una restricción, así que se prueba sobre el enum, que es de
 * donde beben el formulario y el servicio.
 */
test('servicios profesionales, libre nombramiento y elección popular no marcan', function () {
    expect(TipoNombramiento::SERVICIOS_PROFESIONALES->admiteMarcacion())->toBeFalse()
        ->and(TipoNombramiento::LIBRE_NOMBRAMIENTO->admiteMarcacion())->toBeFalse()
        ->and(TipoNombramiento::ELECCION_POPULAR->admiteMarcacion())->toBeFalse();
});

test('el resto de modalidades sí admite marcación', function () {
    expect(TipoNombramiento::PERMANENTE->admiteMarcacion())->toBeTrue()
        ->and(TipoNombramiento::PROVISIONAL->admiteMarcacion())->toBeTrue()
        ->and(TipoNombramiento::SERVICIOS_OCASIONALES->admiteMarcacion())->toBeTrue()
        // Los obreros quedan editables: entre ellos unos marcan y otros no.
        ->and(TipoNombramiento::CODIGO_TRABAJO->admiteMarcacion())->toBeTrue();
});

test('lo que no admite marcación tampoco la trae por defecto', function () {
    foreach (TipoNombramiento::cases() as $tipo) {
        if (! $tipo->admiteMarcacion()) {
            expect($tipo->puedeMarcarPorDefecto())
                ->toBeFalse($tipo->value.' no debería marcar por defecto');
        }
    }
});

test('el default de marcación sigue la regla que dio Talento Humano', function () {
    expect(TipoNombramiento::PERMANENTE->puedeMarcarPorDefecto())->toBeTrue()
        ->and(TipoNombramiento::PROVISIONAL->puedeMarcarPorDefecto())->toBeTrue()
        ->and(TipoNombramiento::SERVICIOS_OCASIONALES->puedeMarcarPorDefecto())->toBeTrue()
        // Obrero: admite marcación, pero no se pre-marca. TH lo decide.
        ->and(TipoNombramiento::CODIGO_TRABAJO->puedeMarcarPorDefecto())->toBeFalse();
});
